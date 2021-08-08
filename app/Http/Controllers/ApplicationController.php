<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationCreateRequest;
use App\Http\Requests\ApplicationUpdateRequest;
use App\Repositories\Application\IApplicationRepository;
use App\Repositories\ReadApplication\IReadApplicationRepository;
use App\Repositories\User\IUserRepository;
use App\Services\ApplicationService;
use Illuminate\Support\Facades\Auth;

class ApplicationController extends Controller
{
    protected $applicationRepository;

    protected $readApplicationRepository;

    protected $userRepository;

    /**
     * ApplicationController constructor.
     *
     * @param IApplicationRepository     $applicationRepository
     * @param IReadApplicationRepository $readApplicationRepository
     * @param IUserRepository            $userRepository
     */
    public function __construct(
        IApplicationRepository $applicationRepository,
        IReadApplicationRepository $readApplicationRepository,
        IUserRepository $userRepository,
        ApplicationService $applicationService
    ) {
        $this->applicationRepository = $applicationRepository;
        $this->readApplicationRepository = $readApplicationRepository;
        $this->userRepository = $userRepository;

        $this->applicationService = $applicationService;
    }

    public function index()
    {
        $user = $this->userRepository->getBySub(Auth::id());
        $applications = $this->applicationService->fetchApplications($user);

        //既読処理
        $this->readApplicationRepository->create($applications);

        return view('application.index', compact('applications'));
    }

    public function store(ApplicationCreateRequest $request)
    {
        $auth0User = Auth::user();
        $user = $this->userRepository->getBySub($auth0User->sub);

        if ($this->applicationRepository->getOngoingApplication($user->id)) {
            return redirect()->route('profile.index')->with(['alert' => '既に申請済みです。']);
        }

        if (!$this->applicationRepository->create($user->id, $request->mentor_id)) {
            return redirect()->route('profile.index')->with(['alert' => '申請に失敗しました。']);
        }

        return redirect()->route('profile.index')->with(['success' => "申請しました！\nメンターの承認をお待ちください。"]);
    }

    public function update(ApplicationUpdateRequest $request)
    {
        $mentorId = $request->mentor_id;

        if ($request->has('rejected')) {
            //application statusを3に更新
            $menteeId = $request->rejected;
            $this->applicationRepository->updateRejectedApplication($mentorId, $menteeId);
            return redirect()->route('application.index')->with(['success' => '応募を拒否しました。']);
        } elseif ($request->has('approved')) {
            $mentees = $request->userId;

            foreach ($mentees as $menteeId) {
                //aplication statusを2に更新
                $this->applicationRepository->updateApprovedApplication($mentorId, $menteeId);
                //read_application　既読済テーブルから既読の情報を消す
                //@TODO:delete()メソッドの実装
//                $application = $this->applicationRepository->getOngoingApplication($menteeId);
//                $this->readApplicationRepository->delete($application->id, $menteeId);
            }
            return redirect()->route('application.index')->with(['success' => '応募を承認しました。']);
        }
    }
}
