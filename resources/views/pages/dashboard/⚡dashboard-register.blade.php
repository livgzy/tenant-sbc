<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\UserTenant;

new class extends Component
{
    #[Validate('required|min:11|max:15')]
    public $nim = '';

    #[Validate('required|min:3|max:100')]
    public $name = '';

    #[Validate('required|string')]
    public $prodi = '';

    #[Validate('required|min:1|max:14')]
    public $semester = '';
    
    #[Validate('required|email|unique:users,email')]
    public $email = '';
    
    #[Validate('required|min:10|max:15')]
    public $phone = '';
    
    #[Validate('required|min:6|confirmed')]
    public $password = '';

    public $password_confirmation = '';

    public function register()
    {
        $validated = $this->validate();

        $user = UserTenant::create([
            'nim' => $this->nim,
            'name' => $this->name,
            'prodi' => $this->prodi,
            'semester' => $this->semester,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'phone' => $this->phone,
        ]);;

        Auth::guard('tenant')->login($user);
        return redirect('/reservasi');
    }

    public function render()
    {
        return $this->view()->layout('layouts::guest', ['title' => 'Student Business Corner | Register']); 
    }
};
?>

<div class="min-h-screen bg-[#f2f4fa] overflow-hidden grid grid-cols-1 md:grid-cols-2">
    <!-- Left Side - Branding -->
    <div class="hidden md:flex items-center justify-center relative bg-white">
        <div class="absolute -left-10 top-1/2 -translate-y-1/2 w-72 h-72 bg-yellow-200 rounded-full opacity-70"></div>
        <div class="absolute left-32 top-28 w-80 h-80 bg-orange-200 rounded-full opacity-70"></div>
        <div class="relative z-10">
            <img src="{{ asset('storage/logo/logo_ucic.png') }}" class="w-100 h-100 object-contain" alt="">
        </div>
    </div>

    <!-- Right Side - Register Form -->
    <div class="flex items-center justify-center px-6 py-12 md:px-12 relative">
        <div class="relative z-10 w-full max-w-md">
            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <!-- Header -->
                <div class="text-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-800">Register</h2>
                    <p class="text-gray-500 text-sm mt-1">Daftar untuk mulai menggunakan Student Business Corner</p>
                </div>

                <!-- Form -->
                <form wire:submit.prevent="register" class="space-y-4">
                    <!-- NIM -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            NIM <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            
                            <input type="text" 
                                   wire:model="nim"
                                   placeholder="Contoh: 20251020024"
                                   class="w-full pl-4 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                        </div>
                        @error('nim') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Nama Lengkap & Prodi -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Nama Lengkap <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                
                                <input type="text" 
                                       wire:model="name"
                                       placeholder="John Doe"
                                       class="w-full pl-4 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Program Studi <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                
                                <select wire:model="prodi" 
                                        class="w-full pl-4 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition appearance-none">
                                    <option value="">Pilih Prodi</option>
                                    <option value="Teknik Informatika">Teknik Informatika</option>
                                    <option value="Sistem Informasi">Sistem Informasi</option>
                                    <option value="Manajemen Informatika">Manajemen Informatika</option>
                                    <option value="Desian Komunikasi Visual">Desian Komunikasi Visual</option>
                                    <option value="Manajemen">Manajemen</option>
                                    <option value="Manajemen Bisnis">Manajemen Bisnis</option>
                                    <option value="Akuntansi">Akuntansi</option>
                                    <option value="Bisnis Digital">Bisnis Digital</option>
                                </select>
                                
                            </div>
                            @error('prodi') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Semester & Email -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Semester <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                
                                <input type="number" 
                                       wire:model="semester"
                                       min="1" max="14"
                                       placeholder="2"
                                       class="w-full pl-4 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            @error('semester') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                
                                <input type="email" 
                                       wire:model="email"
                                       placeholder="contoh@email.com"
                                       class="w-full pl-4 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Phone & Password -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                No. Telepon <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                
                                <input type="tel" 
                                       wire:model="phone"
                                       placeholder="081234567890"
                                       class="w-full pl-4 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                               
                                <input type="password" 
                                       wire:model="password"
                                       placeholder="Minimal 6 karakter"
                                       class="w-full pl-4 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Konfirmasi Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            
                            <input type="password" 
                                   wire:model="password_confirmation"
                                   placeholder="Ulangi password"
                                   class="w-full pl-4 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            class="w-full bg-orange-600 hover:bg-orange-700 text-white font-semibold py-3 rounded-xl transition duration-200 flex items-center justify-center gap-2">
                        <flux:icon.loading wire:loading wire:loading class="size-6 text-white" />
                        
                        <span wire:loading.remove class="text-white font-semibold ">Daftar Sekarang</span>
                    </button>

                    <!-- Login Link -->
                    <div class="text-center mt-4">
                        <p class="text-sm text-gray-600">
                            Sudah punya akun? 
                            <a href="{{ route('login') }}" wire:navigate class="text-orange-600 font-semibold hover:text-orange-700 transition">
                                Login disini
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>