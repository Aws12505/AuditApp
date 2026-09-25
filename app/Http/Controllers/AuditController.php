<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\CameraForm;
use App\Models\CameraFormNote;
use App\Models\CameraFormNoteAttachment;
use Carbon\Carbon;
class AuditController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user) abort(401);

        $allowedStoreIds = $user->allowedStoreIdsCached();

        $audits = Audit::with(['store', 'user'])
            ->whereIn('store_id', $allowedStoreIds)
            ->paginate(15);

        return Inertia::render('Audits/Index', ['audits' => $audits]);
    }

    public function show(Audit $audit)
    {
        $user = Auth::user();
        if (!$user) abort(401);

        if (!$user->canAccessAudit($audit)) {
            abort(403, 'Unauthorized');
        }

        $audit->load(['store', 'user', 'cameraForms']);

        return Inertia::render('Audits/Show', ['audit' => $audit]);
    }


    public function getDataByDateRange(Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));

        $audits = Audit::with([
            'store:id,store',
            'user:id,email',
            'cameraForms.entity:id,entity_label',
            'cameraForms.notes.attachments'
        ])
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        return response()->json(
            $audits->map(function ($audit) {

                return [
                    'id' => $audit->id,
                    'date' => $audit->date,
                    'store' => $audit->store?->store,
                    'email' => $audit->user?->email,

                    'camera_forms' => $audit->cameraForms->map(function ($form) {

                        return [
                            'id' => $form->id,
                            'entity_label' => $form->entity?->entity_label,
                            'rating_id' => $form->rating_id,

                            'notes' => $form->notes->map(function ($note) {

                                return [
                                    'id' => $note->id,
                                    'note' => $note->note,

                                    'attachments' => $note->attachments->map(function ($attachment) {
                                        return [
                                            'id' => $attachment->id,
                                            'path' => $attachment->path
                                        ];
                                    })
                                ];

                            })
                        ];

                    })
                ];

            })
        );
    }
}
