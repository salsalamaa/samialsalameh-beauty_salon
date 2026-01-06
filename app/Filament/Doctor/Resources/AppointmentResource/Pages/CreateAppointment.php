<?php

namespace App\Filament\Doctor\Resources\AppointmentResource\Pages;

use App\Filament\Doctor\Resources\AppointmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Patient;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // --------------------------------------------
        // 1) Parse appointment time
        // --------------------------------------------
        $appointmentTime = \Carbon\Carbon::parse($data['date']);
        $hour = (int) $appointmentTime->format('H');

        // --------------------------------------------
        // 2) Allow appointments only 10:00–22:00
        // --------------------------------------------
        if ($hour < 10 || $hour >= 22) {
            Notification::make()
                ->title('Hata')
                ->body(__('Randevular sadece 10:00 - 22:00 saatleri arasında alınabilir.'))
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'date' => '',
            ]);
        }

        // --------------------------------------------
        // 3) Prevent overlapping appointments ±30 min
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
                ->body(__('Bu saat aralığında zaten bir randevu bulunmaktadır.'))
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'date' => '',
            ]);
        }

        // --------------------------------------------
        // 4) Create or use existing patient
        // --------------------------------------------
        $patient = \App\Models\Patient::firstOrCreate(
            ['tc_id' => $data['patient']['tc_id']],
            $data['patient']
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
