<?php

namespace App\Modules\Class\Services;

use App\Modules\Class\Models\ClassScheduleSlot;
use App\Modules\Class\Models\SchoolClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassScheduleService
{
    /**
     * ذخیره کامل برنامه کلاس + برگرداندن تداخل‌ها.
     *
     * @param  list<array{day_of_week: int, start_time: string, end_time: string, room?: string|null}>  $slots
     * @return array{slots: Collection, conflicts: list<array<string, mixed>>}
     */
    public function syncSchedule(SchoolClass $class, array $slots): array
    {
        foreach ($slots as $i => $slot) {
            if ($slot['start_time'] >= $slot['end_time']) {
                throw ValidationException::withMessages([
                    "slots.$i.end_time" => 'ساعت پایان باید بعد از شروع باشد.',
                ]);
            }
        }

        // تداخل داخل خود برنامه
        $internal = $this->findInternalConflicts($slots);
        if ($internal) {
            throw ValidationException::withMessages([
                'slots' => 'در خود برنامه کلاس تداخل زمانی وجود دارد: '.$internal[0]['message'],
            ]);
        }

        $external = $this->findExternalConflicts($class, $slots);

        ClassScheduleSlot::query()->where('school_class_id', $class->id)->delete();

        $created = collect();
        foreach ($slots as $slot) {
            $created->push(ClassScheduleSlot::create([
                'id'              => (string) Str::uuid(),
                'school_class_id' => $class->id,
                'day_of_week'     => (int) $slot['day_of_week'],
                'start_time'      => $slot['start_time'],
                'end_time'        => $slot['end_time'],
                'room'            => $slot['room'] ?? null,
            ]));
        }

        return [
            'slots'     => $created,
            'conflicts' => $external,
        ];
    }

    /**
     * @param  list<array{day_of_week: int, start_time: string, end_time: string, room?: string|null}>  $slots
     * @return list<array{message: string}>
     */
    protected function findInternalConflicts(array $slots): array
    {
        $conflicts = [];
        $n = count($slots);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ((int) $slots[$i]['day_of_week'] !== (int) $slots[$j]['day_of_week']) {
                    continue;
                }
                if ($this->timesOverlap($slots[$i]['start_time'], $slots[$i]['end_time'], $slots[$j]['start_time'], $slots[$j]['end_time'])) {
                    $conflicts[] = [
                        'message' => "ردیف ".($i + 1)." با ردیف ".($j + 1)." هم‌پوشانی دارد",
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * تداخل با کلاس‌های دیگر همان مدرسه (معلم یا اتاق)
     *
     * @param  list<array{day_of_week: int, start_time: string, end_time: string, room?: string|null}>  $slots
     * @return list<array{type: string, message: string, other_class: string}>
     */
    public function findExternalConflicts(SchoolClass $class, array $slots): array
    {
        $conflicts = [];

        $others = ClassScheduleSlot::query()
            ->whereHas('schoolClass', function ($q) use ($class) {
                $q->where('school_id', $class->school_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $class->id);
            })
            ->with('schoolClass:id,name,teacher_user_id')
            ->get();

        foreach ($slots as $slot) {
            foreach ($others as $other) {
                if ((int) $other->day_of_week !== (int) $slot['day_of_week']) {
                    continue;
                }
                if (! $this->timesOverlap($slot['start_time'], $slot['end_time'], $other->start_time, $other->end_time)) {
                    continue;
                }

                // تداخل معلم
                if ($class->teacher_user_id && $other->schoolClass->teacher_user_id
                    && $class->teacher_user_id === $other->schoolClass->teacher_user_id) {
                    $conflicts[] = [
                        'type'        => 'teacher',
                        'message'     => "تداخل معلم با کلاس «{$other->schoolClass->name}»",
                        'other_class' => $other->schoolClass->name,
                    ];
                }

                // تداخل اتاق
                $room = $slot['room'] ?? null;
                if ($room && $other->room && $room === $other->room) {
                    $conflicts[] = [
                        'type'        => 'room',
                        'message'     => "تداخل اتاق «{$room}» با کلاس «{$other->schoolClass->name}»",
                        'other_class' => $other->schoolClass->name,
                    ];
                }
            }
        }

        // یکتا کردن
        $unique = [];
        foreach ($conflicts as $c) {
            $unique[$c['type'].'|'.$c['message']] = $c;
        }

        return array_values($unique);
    }

    protected function timesOverlap(string $s1, string $e1, string $s2, string $e2): bool
    {
        return $s1 < $e2 && $s2 < $e1;
    }
}
