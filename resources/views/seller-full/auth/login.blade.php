<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login · Invoiz</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: { DEFAULT: '#16697A', dark: '#0E4A57', soft: '#EAF4F3' }, secondary: '#F0A202' }, fontFamily: { sans: ['Segoe UI', 'system-ui', 'sans-serif'] } } } }
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="h-full bg-basebg font-sans" style="background-color:#F7F6F2;">
    <div class="flex min-h-full">
        {{-- Brand panel --}}
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden p-12 lg:flex"
             style="background: linear-gradient(140deg, #16697A 0%, #0E4A57 100%);">
            <div class="flex items-center gap-3">
                <div class="flex h-14 items-center justify-center rounded-xl bg-white px-4">
                    <img src="{{ asset('images/invoiz-logo.png') }}" alt="Invoiz" class="h-10 w-auto object-contain">
                </div>
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-white/60">Login with Invoiz now</div>
            </div>

            <div>
                <h1 class="text-3xl font-extrabold leading-tight text-white">Grow your store.<br>Ship orders with ease.</h1>
                <p class="mt-4 max-w-md text-white/70">
                    Shop online with Invoiz where you can manage your inventory, orders, vouchers, reports, and customer and seller conversations —
                    all connected to the same Invoiz account.
                </p>
                <div class="mt-8 grid grid-cols-3 gap-4">
                    <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
                        <div class="text-2xl font-extrabold text-white">{{ number_format($stats['products']) }}</div>
                        <div class="mt-1 text-xs text-white/60">Products</div>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
                        <div class="text-2xl font-extrabold text-white">{{ number_format($stats['orders']) }}</div>
                        <div class="mt-1 text-xs text-white/60">Orders</div>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
                        <div class="text-2xl font-extrabold text-white">&#8369;{{ number_format($stats['sales']) }}</div>
                        <div class="mt-1 text-xs text-white/60">Sales</div>
                    </div>
                </div>
            </div>

            <div class="text-xs text-white/40">Invoiz · v1.0 · Seller</div>
        </div>

        {{-- Form panel --}}
        <div class="flex w-full items-center justify-center px-6 py-12 lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <div class="flex h-11 items-center justify-center rounded-lg border border-borderline bg-white px-3">
                        <img src="{{ asset('images/invoiz-logo.png') }}" alt="Invoiz" class="h-7 w-auto object-contain">
                    </div>
                    <div class="text-[11px] font-semibold uppercase tracking-widest text-ink-light">Seller Center</div>
                </div>

                <h2 class="text-2xl font-extrabold tracking-tight">Login</h2>
                <p class="mt-1 text-sm text-ink-light">Sign in with your Invoiz account.</p>

                <div class="card mt-8 p-6">
                    @if(session('error'))
                        <div class="mb-4 flex items-center gap-2 rounded-2xl border border-warnc/30 bg-red-50 px-4 py-3 text-sm font-semibold text-warnc">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('seller.login.post') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="field-label" for="email">Email Address</label>
                            <input class="input @error('email') border !border-warnc @enderror"
                                   id="email" name="email" type="email" placeholder="account@invoiz.test"
                                   value="{{ old('email') }}" autocomplete="email" required autofocus>
                            @error('email')
                                <p class="mt-1.5 text-xs font-semibold text-warnc">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="field-label" for="password">Password</label>
                            <div class="relative">
                                <input class="input pr-10" id="password" name="password" type="password"
                                       placeholder="••••••••" autocomplete="current-password" required>
                                <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none" aria-label="Toggle password visibility">
                                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12c1.274-4.057 5.065-7 9.542-7 4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg id="eye-off-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 hidden">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-ink-light">
                                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-borderline text-[#16697A]" style="accent-color:#16697A;">
                                Remember me
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary w-full !py-3.5 text-[15px]">Sign in</button>
                    </form>
                </div>

                <p class="mt-6 text-center text-xs text-ink-light">
                    Buyers: go to the <a href="http://127.0.0.1:8000" class="font-semibold text-[#16697A] hover:underline">Invoiz app</a>
                </p>
            </div>
        </div>
    </div>
    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeOffIcon = document.getElementById('eye-off-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
