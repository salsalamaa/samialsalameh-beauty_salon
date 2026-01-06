<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Patient;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Validation\DataAwareRule;
use PhpParser\Node\Expr\UnaryMinus;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    // AppointmentResource::mutateFormDataBeforeCreate()

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // --------------------------------------------
        // 1) Check doctor's available hours (10:00–22:00)
        // --------------------------------------------
        $appointmentTime = \Carbon\Carbon::parse($data['date']);
        $hour = (int) $appointmentTime->format('H');

        if ($hour < 10 || $hour >= 22) {
            Notification::make()
                ->title('Hata')
                ->body('Randevular sadece 10:00 - 22:00 saatleri arasında alınabilir.')
                ->danger()
                ->send();

            $this->halt();
        }

        // --------------------------------------------
        // 2) Check overlapping appointments ±30 minutes
        // --------------------------------------------
        $doctorId = $data['doctor_id'];
        $start = $appointmentTime->copy()->subMinutes(30);
        $end   = $appointmentTime->copy()->addMinutes(30);

        $exists = \App\Models\Appointment::where('doctor_id', $doctorId)
            ->whereBetween('date', [$start, $end])
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Hata')
                ->body('Bu saat aralığında zaten bir randevu bulunmaktadır.')
                ->danger()
                ->send();

            $this->halt();
        }

        // --------------------------------------------
        // 3) Prevent duplicate patients using firstOrCreate
        // --------------------------------------------
        $patient = \App\Models\Patient::firstOrCreate(
            ['tc_id' => $data['patient']['tc_id']],

        );

        $data['patient_id'] = $patient->id;
        unset($data['patient']);

        return $data;
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
