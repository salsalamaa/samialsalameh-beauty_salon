<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\Traitment;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\ClosedDay;
use Livewire\Features\SupportTesting\Render;

use function Symfony\Component\String\s;

class TakeApp extends Component
{
    public $appointmentDate;
    public $appointmentTime;
    public $availableTimes = [];
    public $name, $surname, $tc_id, $email, $birth_date, $phone;
    public $doctor_id, $traitment_id, $date, $message;

    public $doctors = [];
    public $traitments = [];

    public function mount()
    {
        $this->doctors = User::whereHas('roles', function ($q) {
        $q->where('id', 2); // doktor rolü id'si
        })->select('id', 'name')->get();
        $this->traitments = Traitment::all();
    }

    private function isClosedDay($date)
    {
        return ClosedDay::where(function ($q) use ($date) {
            $q->whereDate('date', $date)
              ->orWhere(function ($q) use ($date) {
                  $q->whereDate('from_date', '<=', $date)
                    ->whereDate('to_date', '>=', $date);
              });
        })->exists();
    }

    private function generateAvailableTimes($date)
{
    if (!$date || !$this->doctor_id || !$this->traitment_id) {
        return [];
    }

    // Prevent booking on closed days
    if ($this->isClosedDay($date)) {
        return [];
    }

    $start = Carbon::createFromTime(10, 0); // بداية الدوام
    $end   = Carbon::createFromTime(22, 0); // نهاية الدوام

    $duration = optional(
        $this->traitments->find($this->traitment_id)
    )->duration ?? 30;

    $buffer = 10;

    $slots = [];

    while ($start->copy()->addMinutes($duration)->lte($end)) {

        $dateTime = Carbon::parse("$date ".$start->format('H:i'));

        $exists = \App\Models\Appointment::where('doctor_id', $this->doctor_id)
            ->whereBetween('date', [
                $dateTime->copy()->subMinutes($buffer),
                $dateTime->copy()->addMinutes($duration + $buffer),
            ])
            ->exists();

        if (!$exists) {
            $slots[] = $start->format('H:i');
        }

        $start->addMinutes(15); // خطوة ذكية
    }

    return $slots;
}
public function updatedAppointmentDate($date)
{
    $this->appointmentTime = null;
    $this->availableTimes = $this->generateAvailableTimes($date);
}

public function updatedDoctorId($value)
{
    $this->appointmentTime = null;

    if ($this->appointmentDate) {
        $this->availableTimes = $this->generateAvailableTimes($this->appointmentDate);
    }
}

public function updatedTraitmentId($value)
{
    $this->appointmentTime = null;

    if ($this->appointmentDate) {
        $this->availableTimes = $this->generateAvailableTimes($this->appointmentDate);
    }
}
    public $isSubmitted = false;

    public function submit()
    {
        $this->validate([
            'appointmentDate' => 'required|date',
            'appointmentTime' => 'required',
        ]);

        $this->date = Carbon::parse(
            $this->appointmentDate . ' ' . $this->appointmentTime
        );
       $this->validate([
            'name' => 'required|string|min:2|max:50',
            'surname' => 'required|string|min:2|max:50',
            'tc_id' => 'required|string|min:11|max:11|regex:/^[0-9]+$/',
            'email' => 'required|email',
            'birth_date' => 'required|date|before_or_equal:' . \Carbon\Carbon::now()->subYears(18)->format('Y-m-d') . '|after_or_equal:' . \Carbon\Carbon::now()->subYears(100)->format('Y-m-d'),
            'phone' => 'required|string|min:10|max:10|regex:/^[0-9]+$/',
            'doctor_id' => 'required',
            'traitment_id' => 'required',
            'date' => 'required|date|after_or_equal:today|before_or_equal:' . now()->addYear()->format('Y-m-d'),
        ]);

        $patient = Patient::where('tc_id', $this->tc_id)
            ->orWhere('email', $this->email)
            ->first();

        if (!$patient) {
            $patient = Patient::create([
                'name' => $this->name,
                'surname'=> $this->surname,
                'tc_id' => $this->tc_id,
                'email' => $this->email,
                'birth_date' => $this->birth_date,
                'phone' => $this->phone,
            ]);
        }

        // Seçilen saat çalışma saatleri içinde mi? (10:00 - 22:00)
        $selected = \Carbon\Carbon::parse($this->date);
        $hour = $selected->format('H:i');

        if ($hour < '10:00' || $hour > '22:00') {
            $this->addError('date', 'Randevu saatleri 10:00 ile 22:00 arasındadır. Lütfen geçerli bir saat seçiniz.');
            return;
        }

        // Pazar günü randevu yapılamaz
        if ($selected->isSunday()) {
            $this->addError('date', 'Pazar günü randevu alınamaz. Lütfen haftaiçi bir gün seçiniz.');
            return;
        }

        // Check if day is closed
        if ($this->isClosedDay($selected->format('Y-m-d'))) {
            $this->addError('date', 'Seçilen gün merkez kapalı. Lütfen farklı bir gün seçiniz.');
            return;
        }

        // Doktorun ±30 dakika içinde başka randevusu var mı kontrolü
        $start = $selected->copy()->subMinutes(30);
        $end   = $selected->copy()->addMinutes(30);

        $existing = Appointment::where('doctor_id', $this->doctor_id)
            ->whereBetween('date', [$start, $end])
            ->exists();

        if ($existing) {
            $this->addError('date', 'Seçtiğiniz saatten 30 dakika önce veya sonra doktorun başka bir randevusu bulunmaktadır. Lütfen farklı bir saat seçiniz.');
            return;
        }

        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor_id,
            'traitment_id' => $this->traitment_id,
            'date' => $this->date,
            'status' => 'waiting',
        ]);

        // session()->flash('message', 'Randevunuz başarıyla oluşturuldu.');
        // $this->reset();

         $this->isSubmitted = true;
    }

    public function newAppointment()
    {
        $this->reset([
            'name', 'surname', 'tc_id', 'birth_date', 'email', 'phone',
            'doctor_id', 'traitment_id', 'date', 'isSubmitted'
        ]);
    }

    public function render()
    {
        return view('livewire.take-app');
    }
}
