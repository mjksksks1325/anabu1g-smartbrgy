<?php

namespace App\Livewire\Settings;

use App\Actions\VerifySupervisorPassword;
use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class DeleteUserForm extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout, VerifySupervisorPassword $supervisorPasswordVerifier): void
    {
        $validated = $this->validate(['password' => ['required', 'string', 'max:255']]);
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canLoginAsPersonnel(), 403);
        $rateLimitKey = 'supervisor-record-approval:'.$user->id.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->password = '';
            $this->addError('password', 'Masyadong maraming maling password. Subukan ulit pagkalipas ng ilang sandali.');

            return;
        }

        if (! $supervisorPasswordVerifier->passes($validated['password'])) {
            RateLimiter::hit($rateLimitKey, 60);
            $this->password = '';
            $this->addError('password', 'Hindi tama ang password ng active Super Admin.');

            return;
        }

        RateLimiter::clear($rateLimitKey);

        tap($user, $logout(...))->forceDelete();

        $this->redirect('/', navigate: true);
    }
}
