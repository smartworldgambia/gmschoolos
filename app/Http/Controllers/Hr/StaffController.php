<?php

namespace App\Http\Controllers\Hr;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobPosition;
use App\Models\PerformanceRecord;
use App\Models\Staff;
use App\Models\StaffContract;
use App\Models\StaffDocument;
use App\Models\StaffLeaveRequest;
use App\Models\User;
use App\Services\Hr\HrAccess;
use App\Services\Hr\LeaveService;
use App\Services\Ops\Ops;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Staff directory, profiles, contracts, documents (private disk) and performance records. */
class StaffController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public const EMPLOYMENT = ['permanent', 'contract', 'part_time', 'volunteer'];

    public const CONTRACT_TYPES = ['permanent', 'fixed_term', 'part_time', 'probation'];

    public const DOC_TYPES = ['contract', 'id', 'certificate', 'cv', 'other'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'active');
        $dept = $request->integer('department') ?: null;

        $staff = Staff::query()->with(['department:id,name', 'position:id,title'])
            ->when(in_array($status, ['active', 'inactive', 'exited'], true), fn ($b) => $b->where('status', $status))
            ->when($dept, fn ($b) => $b->where('department_id', $dept))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('first_name', 'like', "%$q%")->orWhere('last_name', 'like', "%$q%")->orWhere('staff_number', 'like', "%$q%")))
            ->orderBy('last_name')->orderBy('first_name')->paginate(25)->withQueryString();

        return Inertia::render('Hr/Staff', array_merge($this->shellProps(), [
            'staff' => $staff->through(fn (Staff $s) => [
                'id' => $s->id, 'staff_number' => $s->staff_number, 'name' => $s->full_name, 'department' => $s->department?->name,
                'position' => $s->position?->title ?? $s->role_title, 'phone' => $s->phone, 'status' => $s->status, 'has_login' => $s->user_id !== null,
            ]),
            'filters' => ['q' => $q, 'status' => $status, 'department' => $dept],
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'positions' => JobPosition::query()->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'logins' => $this->freeLogins(),
            'employmentTypes' => self::EMPLOYMENT,
            'can' => ['manage' => $request->user()->can('hr.manage')],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $this->staffRules($request, null);

        return $this->attempt(function () use ($v) {
            $this->assertLoginFree($v['user_id'] ?? null, null);
            $staff = DB::transaction(function () use ($v) {
                $number = Ops::nextNumber('staff', 'STF', 'staff_number');

                return Staff::query()->create($v + ['staff_number' => $number, 'status' => 'active']);
            });
            Ops::audit('hr.staff.created', 'Staff record created', ['staff_id' => $staff->id]);

            return redirect()->route('hr.staff.show', $staff)->with('success', 'ok.hr.staffSaved');
        });
    }

    public function show(Request $request, Staff $staff, LeaveService $leave): Response
    {
        $user = $request->user();
        $pay = HrAccess::seesPay($user);
        $staff->load(['department:id,name', 'position:id,title']);

        return Inertia::render('Hr/StaffShow', array_merge($this->shellProps(), [
            'staff' => [
                'id' => $staff->id, 'staff_number' => $staff->staff_number, 'first_name' => $staff->first_name, 'last_name' => $staff->last_name, 'name' => $staff->full_name,
                'email' => $staff->email, 'phone' => $staff->phone, 'role_title' => $staff->role_title, 'status' => $staff->status,
                'department_id' => $staff->department_id, 'department' => $staff->department?->name, 'job_position_id' => $staff->job_position_id, 'position' => $staff->position?->title,
                'hired_on' => $staff->hired_on?->toDateString(), 'exited_on' => $staff->exited_on?->toDateString(), 'employment_type' => $staff->employment_type,
                'emergency_contact_name' => $staff->emergency_contact_name, 'emergency_contact_phone' => $staff->emergency_contact_phone, 'user_id' => $staff->user_id,
                'bank_name' => $pay || $user->can('hr.manage') ? $staff->bank_name : null,
                'bank_account_masked' => ($pay || $user->can('hr.manage')) && $staff->bank_account ? str_repeat('•', max(0, mb_strlen($staff->bank_account) - 4)).mb_substr($staff->bank_account, -4) : null,
            ],
            'contracts' => $staff->contracts()->with('position:id,title')->get()->map(fn (StaffContract $c) => [
                'id' => $c->id, 'contract_type' => $c->contract_type, 'position' => $c->position?->title, 'starts_on' => $c->starts_on?->toDateString(), 'ends_on' => $c->ends_on?->toDateString(),
                'status' => $c->status, 'notes' => $c->notes, 'basic_salary' => $pay ? (int) $c->basic_salary : null,
            ]),
            'documents' => $user->can('hr.manage')
                ? StaffDocument::query()->where('staff_id', $staff->id)->latest('id')->get()->map(fn (StaffDocument $d) => [
                    'id' => $d->id, 'type' => $d->type, 'title' => $d->title, 'size' => $d->size, 'expires_on' => $d->expires_on?->toDateString(), 'created_at' => $d->created_at?->toDateString(),
                ])
                : [],
            'performance' => $user->can('hr.performance')
                ? PerformanceRecord::query()->with('reviewer:id,name')->where('staff_id', $staff->id)->latest('id')->get()->map(fn (PerformanceRecord $p) => [
                    'id' => $p->id, 'period_label' => $p->period_label, 'rating' => (int) $p->rating, 'strengths' => $p->strengths, 'improvements' => $p->improvements,
                    'goals' => $p->goals, 'reviewer' => $p->reviewer?->name, 'created_at' => $p->created_at?->toDateString(),
                ])
                : null,
            'leave' => StaffLeaveRequest::query()->where('staff_id', $staff->id)->latest('id')->limit(10)->get()->map(fn (StaffLeaveRequest $l) => [
                'id' => $l->id, 'leave_type' => $l->leave_type, 'starts_on' => $l->starts_on?->toDateString(), 'ends_on' => $l->ends_on?->toDateString(), 'days' => $l->days, 'status' => $l->status,
            ]),
            'balances' => $leave->balances($staff),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'positions' => JobPosition::query()->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'logins' => $this->freeLogins($staff->user_id),
            'employmentTypes' => self::EMPLOYMENT,
            'contractTypes' => self::CONTRACT_TYPES,
            'docTypes' => self::DOC_TYPES,
            'can' => ['manage' => $user->can('hr.manage'), 'pay' => $user->can('payroll.manage'), 'performance' => $user->can('hr.performance')],
        ]));
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $v = $this->staffRules($request, $staff) + $request->validate(['status' => ['required', 'in:active,inactive,exited']]);

        return $this->attempt(function () use ($v, $staff) {
            $this->assertLoginFree($v['user_id'] ?? null, $staff->id);
            if ($v['status'] === 'exited') {
                $v['exited_on'] ??= today()->toDateString();
            }
            // keep an existing bank detail when the form leaves the field empty (the form never receives the real number)
            foreach (['bank_name', 'bank_account'] as $k) {
                if (empty($v[$k])) {
                    unset($v[$k]);
                }
            }

            DB::transaction(function () use ($staff, $v) {
                $staff->update($v);
                if ($v['status'] === 'exited') {
                    StaffContract::query()->where('staff_id', $staff->id)->where('status', 'active')->update(['status' => 'ended', 'ends_on' => DB::raw('COALESCE(ends_on, \''.today()->toDateString().'\')')]);
                }
            });
            Ops::audit('hr.staff.updated', 'Staff record updated', ['staff_id' => $staff->id, 'status' => $v['status']]);
        }, 'ok.hr.staffSaved');
    }

    // ------------------------------------------------------------------ contracts (salary needs payroll.manage)

    public function storeContract(Request $request, Staff $staff): RedirectResponse
    {
        $v = $request->validate([
            'contract_type' => ['required', Rule::in(self::CONTRACT_TYPES)],
            'job_position_id' => ['nullable', $this->inSchool('job_positions')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'basic_salary' => ['required', 'integer', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->attempt(function () use ($v, $staff, $request) {
            if ($v['contract_type'] === 'fixed_term' && empty($v['ends_on'])) {
                throw AcademicRuleException::because('err.hr.contractEnd');
            }
            DB::transaction(function () use ($v, $staff, $request) {
                $active = StaffContract::query()->where('staff_id', $staff->id)->where('status', 'active')->lockForUpdate()->get();
                if ($active->contains(fn ($c) => $c->starts_on->gte($v['starts_on']))) {
                    throw AcademicRuleException::because('err.hr.contractOrder');
                }
                $before = \Illuminate\Support\Carbon::parse($v['starts_on'])->subDay()->toDateString();
                foreach ($active as $c) {
                    $c->update(['status' => 'ended', 'ends_on' => $c->ends_on && $c->ends_on->toDateString() < $before ? $c->ends_on : $before]);
                }
                $contract = StaffContract::query()->create($v + ['staff_id' => $staff->id, 'status' => 'active', 'created_by' => $request->user()->id]);
                Ops::audit('hr.contract.created', 'Contract added for '.$staff->full_name, ['staff_id' => $staff->id, 'contract_id' => $contract->id, 'type' => $v['contract_type']]);
            });
        }, 'ok.hr.contractSaved');
    }

    public function endContract(Request $request, StaffContract $contract): RedirectResponse
    {
        $v = $request->validate(['ends_on' => ['nullable', 'date'], 'outcome' => ['required', 'in:ended,terminated']]);

        return $this->attempt(function () use ($v, $contract) {
            if ($contract->status !== 'active') {
                throw AcademicRuleException::because('err.hr.contractNotActive');
            }
            $end = $v['ends_on'] ?? today()->toDateString();
            if ($end < $contract->starts_on->toDateString()) {
                throw AcademicRuleException::because('err.hr.contractDates');
            }
            $contract->update(['status' => $v['outcome'], 'ends_on' => $end]);
            Ops::audit('hr.contract.ended', 'Contract '.$v['outcome'], ['staff_id' => $contract->staff_id, 'contract_id' => $contract->id]);
        }, 'ok.hr.contractEnded');
    }

    // ------------------------------------------------------------------ documents

    public function storeDocument(Request $request, Staff $staff): RedirectResponse
    {
        $v = $request->validate([
            'type' => ['required', Rule::in(self::DOC_TYPES)],
            'title' => ['required', 'string', 'max:190'],
            'expires_on' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ]);

        return $this->attempt(function () use ($v, $staff, $request) {
            $file = $request->file('file');
            $path = $file->storeAs('staff-docs/'.$staff->school_id, Str::uuid().'.'.$file->extension(), 'local');
            $doc = StaffDocument::query()->create([
                'staff_id' => $staff->id, 'type' => $v['type'], 'title' => $v['title'], 'path' => $path, 'mime' => $file->getMimeType(),
                'size' => (int) $file->getSize(), 'expires_on' => $v['expires_on'] ?? null, 'uploaded_by' => $request->user()->id,
            ]);
            Ops::audit('hr.document.uploaded', 'Staff document uploaded', ['staff_id' => $staff->id, 'document_id' => $doc->id, 'type' => $v['type']]);
        }, 'ok.hr.documentSaved');
    }

    public function downloadDocument(StaffDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        Ops::audit('hr.document.downloaded', 'Staff document opened', ['staff_id' => $document->staff_id, 'document_id' => $document->id]);

        return Storage::disk('local')->download($document->path, Str::slug($document->title).'.'.pathinfo($document->path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store']);
    }

    public function destroyDocument(StaffDocument $document): RedirectResponse
    {
        return $this->attempt(function () use ($document) {
            Storage::disk('local')->delete($document->path);
            Ops::audit('hr.document.deleted', 'Staff document deleted', ['staff_id' => $document->staff_id, 'document_id' => $document->id]);
            $document->delete();
        }, 'ok.hr.documentRemoved');
    }

    // ------------------------------------------------------------------ performance

    public function storePerformance(Request $request, Staff $staff): RedirectResponse
    {
        $v = $request->validate([
            'period_label' => ['required', 'string', 'max:40'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'improvements' => ['nullable', 'string', 'max:2000'],
            'goals' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->attempt(function () use ($v, $staff, $request) {
            $p = PerformanceRecord::query()->create($v + ['staff_id' => $staff->id, 'reviewer_id' => $request->user()->id]);
            Ops::audit('hr.performance.recorded', 'Performance review recorded', ['staff_id' => $staff->id, 'record_id' => $p->id]);
        }, 'ok.hr.performanceSaved');
    }

    // ------------------------------------------------------------------ helpers

    private function staffRules(Request $request, ?Staff $staff): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_title' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', $this->inSchool('departments')],
            'job_position_id' => ['nullable', $this->inSchool('job_positions')],
            'hired_on' => ['nullable', 'date'],
            'exited_on' => ['nullable', 'date'],
            'employment_type' => ['nullable', Rule::in(self::EMPLOYMENT)],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('school_id', $this->schoolId())],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:60'],
        ]);
    }

    private function assertLoginFree(?int $userId, ?int $exceptStaffId): void
    {
        if ($userId && Staff::query()->where('user_id', $userId)->when($exceptStaffId, fn ($q) => $q->where('id', '!=', $exceptStaffId))->exists()) {
            throw AcademicRuleException::because('err.hr.loginLinked');
        }
    }

    /** School logins that are not yet attached to a staff record (plus the one already attached, when editing). */
    private function freeLogins(?int $keep = null): array
    {
        $taken = Staff::query()->whereNotNull('user_id')->when($keep, fn ($q) => $q->where('user_id', '!=', $keep))->pluck('user_id');

        return User::query()->where('school_id', $this->schoolId())->whereNotIn('id', $taken)->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['parent', 'student']))
            ->orderBy('name')->limit(500)->get(['id', 'name', 'email'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all();
    }
}
