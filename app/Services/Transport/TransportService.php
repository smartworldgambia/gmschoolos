<?php

namespace App\Services\Transport;

use App\Exceptions\AcademicRuleException;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\Term;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Services\Ops\Ops;
use App\Services\Ops\TermCharge;
use Illuminate\Support\Facades\DB;

class TransportService
{
    /** One active ride per learner. Capacity is the vehicle's seat count. */
    public function assign(TransportRoute $route, Student $student, ?int $stopId, ?string $stopName, ?string $startsOn, User $actor): TransportAssignment
    {
        if ($student->status !== 'active') {
            throw AcademicRuleException::because('err.tr.studentInactive');
        }
        if ($route->status !== 'active') {
            throw AcademicRuleException::because('err.tr.routeInactive');
        }

        return DB::transaction(function () use ($route, $student, $stopId, $stopName, $startsOn, $actor) {
            $route = TransportRoute::query()->lockForUpdate()->with('vehicle')->findOrFail($route->id);
            if (TransportAssignment::query()->where('student_id', $student->id)->where('status', 'active')->where('transport_route_id', '!=', $route->id)->exists()) {
                throw AcademicRuleException::because('err.tr.alreadyAssigned');
            }
            $riders = TransportAssignment::query()->where('transport_route_id', $route->id)->where('status', 'active')->lockForUpdate()->count();
            $cap = (int) ($route->vehicle?->capacity ?? 0);
            $existing = TransportAssignment::query()->where('transport_route_id', $route->id)->where('student_id', $student->id)->where('status', 'active')->first();
            if (! $existing && $cap > 0 && $riders >= $cap) {
                throw AcademicRuleException::because('err.tr.full');
            }
            if ($stopId) {
                $stop = $route->stops()->where('id', $stopId)->first();
                if (! $stop) {
                    throw AcademicRuleException::because('err.tr.stopForeign');
                }
                $stopName = $stop->name;
            }

            $row = TransportAssignment::query()->updateOrCreate(
                ['transport_route_id' => $route->id, 'student_id' => $student->id],
                [
                    'transport_stop_id' => $stopId, 'stop_name' => $stopName, 'starts_on' => $startsOn ?: today()->toDateString(),
                    'ends_on' => null, 'status' => 'active',
                ]
            );
            Ops::audit('tr.assigned', 'Learner assigned to '.$route->name, ['assignment_id' => $row->id, 'student_id' => $student->id, 'route_id' => $route->id, 'by' => $actor->id]);

            return $row;
        });
    }

    public function end(TransportAssignment $a, ?string $on): TransportAssignment
    {
        return DB::transaction(function () use ($a, $on) {
            $a = TransportAssignment::query()->lockForUpdate()->findOrFail($a->id);
            if ($a->status !== 'active') {
                throw AcademicRuleException::because('err.tr.notActive');
            }
            $a->update(['status' => 'ended', 'ends_on' => $on ?: today()->toDateString()]);
            Ops::audit('tr.ended', 'Transport assignment ended', ['assignment_id' => $a->id, 'student_id' => $a->student_id]);

            return $a;
        });
    }

    /** Pending term charges, one per rider per term. Source key keeps a second run from double-billing. */
    public function billTerm(Term $term, User $actor): int
    {
        $year = AcademicYear::query()->find($term->academic_year_id);
        $n = 0;
        TransportAssignment::query()->with(['route', 'student'])->where('status', 'active')->orderBy('id')->each(function (TransportAssignment $a) use ($term, $year, $actor, &$n) {
            $fee = (int) ($a->route?->fee_per_term ?: $a->route?->fee_monthly ?: 0);
            if ($fee < 1 || ! $a->student || $a->student->status !== 'active') {
                return;
            }
            $source = 'transport:'.$a->transport_route_id.':'.$term->id;
            TermCharge::ensure($a->student, $source, 'Transport — '.$a->route->name.' ('.$term->name.')', $fee, 'Transport', $year?->id, $term->id, $actor);
            $n++;
        });
        Ops::audit('tr.billed', 'Transport term charges recorded', ['term_id' => $term->id, 'charges' => $n]);

        return $n;
    }
}
