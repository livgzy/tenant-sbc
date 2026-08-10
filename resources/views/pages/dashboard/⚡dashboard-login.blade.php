<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    #[Validate('required')]
    public $nim = '';
    
    #[Validate('required')]
    public $password = '';

    public function login()
    {
        $this->validate();
        
        if (Auth::guard('tenant')->attempt(['nim' => $this->nim, 'password' => $this->password])) {
            $user = Auth::guard('tenant')->user();

            if (Gate::forUser($user)->denies('tenant-dashboard-access')) {
                
                session()->regenerate();
                return redirect('/reservasi');
                // Auth::guard('tenant')->logout();
                // session()->invalidate();
                // session()->regenerateToken();

                // $this->reset(['nim', 'password']); 
                // throw ValidationException::withMessages([
                //     'notAuthorization' => 'Akun Anda tidak memiliki akses tenant.',
                // ]);
            } else{
                session()->regenerate();
                return redirect('/dashboard');
            }
            
        }
        
        $this->addError('failLogin', 'nim atau password salah.');
        $this->reset(['nim', 'password']); 
    }

    public function render()
    {
        return $this->view()->layout('layouts::guest', ['title' => 'Student Business Corner | Login']); 
    }
};
?>

<div class="min-h-screen bg-[#f2f4fa] overflow-hidden grid grid-cols-1 md:grid-cols-2">
    {{-- LEFT SIDE --}}
    <div class="flex items-center justify-center px-6 md:px-20 relative">


        <div class="relative z-10 w-full max-w-md">
            {{-- Heading --}}
            <div class="mb-10">
                <div class="w-12 h-1 bg-orange-500 rounded-full mb-4"></div>

                <h2 class="text-3xl font-bold text-gray-700">
                    Login Tenant Dashboard SBC
                </h2>
            </div>

            {{-- Error --}}
            @error('failLogin')
                <div class="mb-5 bg-red-100 border border-red-300 text-red-600 px-4 py-3 rounded-xl">
                    {{ $message }}
                </div>
            @enderror
            @error('notAuthorization')
                <div class="mb-5 bg-red-100 border border-red-300 text-red-600 px-4 py-3 rounded-xl">
                    {{ $message }}
                </div>
            @enderror
            
            

            {{-- Form --}}
            <form wire:submit="login" class="space-y-6">
            @csrf
                {{-- NIM --}}
                <div>
                    <div class="relative">
                        <input
                            type="text"
                            wire:model="nim"
                            placeholder="NIM"
                            class="w-full bg-white rounded-full border border-gray-200 py-4 px-6 pr-14 shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        >

                        <div class="absolute right-5 top-1/2 -translate-y-1/2 text-gray-400">
                            <flux:icon.user class="size-7 text-black"/>
                        </div>
                    </div>

                    @error('nim')
                        <p class="text-red-500 text-sm mt-2">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <div class="relative">
                        <input
                            type="password"
                            wire:model="password"
                            placeholder="xxxxxxxx"
                            class="w-full bg-white rounded-full border border-gray-200 py-4 px-6 pr-14 shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                        >

                        <div class="absolute right-5 top-1/2 -translate-y-1/2">
                            <flux:icon.eye class="size-7"/>
                        </div>
                    </div>

                    @error('password')
                        <p class="text-red-500 text-sm mt-2">
                            {{ $message }}
                        </p>
                    @enderror
                </div>                
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center bg-orange-600 py-4 rounded-full hover:scale-[1.01] transition duration-200 shadow-lg disabled:opacity-70 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove class="text-white font-semibold ">Login</span>
                    <flux:icon.loading wire:loading class="size-6 text-white" />
                </button>
            </form>
            <div class="mt-4 text-center">
                <!-- Login Link -->
                <div class="text-center mb-2">
                    <p class="text-sm text-gray-600">
                        Tidak punya akun? 
                        <a href="{{ route('register') }}" wire:navigate class="text-orange-600 font-semibold hover:text-orange-700 transition">
                            Register disini
                        </a>
                    </p>
                </div>

                <a href="#" class="text-orange-500 font-semibold hover:underline">
                    Lupa password?
                </a>
            </div>
        </div>
    </div>

    {{-- LEFT SIDE --}}
    <div class="hidden md:flex items-center justify-center relative bg-white">
        <div class="absolute -right-10 top-1/2 -translate-y-1/2 w-72 h-72 bg-yellow-200 rounded-full opacity-70"></div>
        <div class="absolute right-32 top-28 w-80 h-80 bg-orange-200 rounded-full opacity-70"></div>
        <div class="relative z-10">
            <img src="{{ Storage::disk('public')->url('logo/logo_ucic.png') }}" class="w-100 h-100 object-contain" alt="">
        </div>
    </div>
</div>