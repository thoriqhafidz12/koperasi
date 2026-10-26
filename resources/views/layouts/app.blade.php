<x-template-top />

<body id="page-top">
    <div class="loading-page">
        <div class="lds-ring">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>
    <div id="Moduleloader-page" class="jssorl-009-spin">
        <img src="assets/img/spinner.svg">
    </div>
    <div class="bottom-slide">
        <div id="wrapper">

            {{-- Sidebar --}}
            <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

                <a class="sidebar-brand d-flex align-items-center justify-content-center"
                    href="{{ route('dashboard') }}">
                    <div class="sidebar-brand-icon">
                        <img src="{{ url('/') }}/assets/img/logo-koperasi.png" alt="Logo" width="40">
                    </div>
                    <div class="sidebar-brand-text mx-3">AKAR RIMBA KHATULISTIWA</div>
                </a>

                <hr class="sidebar-divider my-0">

                @php
                    // Ambil ID menu yang diizinkan untuk role user saat ini
                    $user = Auth::user();
                    $allowedMenuIds = $user->roleRelation?->menus()->pluck('menu.mId')->toArray() ?? [];

                    $allMenus = App\Models\Menu::where('mIsActive', 1)
                        ->when(!empty($allowedMenuIds), function ($query) use ($allowedMenuIds) {
                            // Jika role punya assignment → filter
                            return $query->whereIn('mId', $allowedMenuIds);
                        })
                        ->orderBy('mOrder')
                        ->get();

                    $parentMenus = $allMenus->whereNull('mParentId');
                    $childMenus = $allMenus->whereNotNull('mParentId')->groupBy('mParentId');

                    // Menu level 3: anak dari menu child (contoh: Master → COA → AKUN/OBJEK)
                    $childIds = $allMenus->whereNotNull('mParentId')->pluck('mId');
                    $grandchildMenus = $allMenus->whereIn('mParentId', $childIds)->groupBy('mParentId');

                    /**
                     * Resolve route name dari mRoute prefix.
                     * Cek: 'dashboard' → Route::has('dashboard') = true
                     *      'users'    → Route::has('users') = false, tapi Route::has('users.index') = true
                     */
                    $resolveRoute = function (?string $mRoute): ?string {
                        if (empty($mRoute)) {
                            return null;
                        }
                        if (\Illuminate\Support\Facades\Route::has($mRoute)) {
                            return $mRoute;
                        }
                        if (\Illuminate\Support\Facades\Route::has($mRoute . '.index')) {
                            return $mRoute . '.index';
                        }
                        return null;
                    };

                    /**
                     * Cek apakah menu ini (atau child/grandchild-nya) sedang aktif.
                     */
                    $isActive = function ($menu, $children) use ($resolveRoute, $grandchildMenus): bool {
                        $routeName = $resolveRoute($menu->mRoute);
                        if ($routeName && request()->routeIs($routeName . '*')) {
                            return true;
                        }
                        foreach ($children as $child) {
                            $childRoute = $resolveRoute($child->mRoute);
                            if ($childRoute && request()->routeIs($childRoute . '*')) {
                                return true;
                            }
                            foreach ($grandchildMenus->get($child->mId, collect()) as $gc) {
                                $gcRoute = $resolveRoute($gc->mRoute);
                                if ($gcRoute && request()->routeIs($gcRoute . '*')) {
                                    return true;
                                }
                            }
                        }
                        return false;
                    };
                @endphp

                @foreach ($parentMenus as $menu)
                    @php
                        $children = $childMenus->get($menu->mId, collect());
                        $routeName = $resolveRoute($menu->mRoute);
                        $hasChildren = $children->isNotEmpty();
                        $isCollapse = $hasChildren || !$routeName;
                        $active = $isActive($menu, $children);
                    @endphp

                    @if ($isCollapse)
                        {{-- Collapse / Dropdown Menu --}}
                        <li class="nav-item {{ $active ? 'active' : '' }}">
                            <a class="nav-link {{ $active ? '' : 'collapsed' }}" href="#" data-toggle="collapse"
                                data-target="#collapseMenu{{ $menu->mId }}"
                                aria-expanded="{{ $active ? 'true' : 'false' }}"
                                aria-controls="collapseMenu{{ $menu->mId }}">
                                <i class="fas fa-fw {{ $menu->mIcon ?: 'fa-folder' }}"></i>
                                <span>{{ $menu->mNama }}</span>
                            </a>
                            <div id="collapseMenu{{ $menu->mId }}" class="collapse {{ $active ? 'show' : '' }}"
                                data-parent="#accordionSidebar">
                                <div class="bg-white py-2 collapse-inner rounded">
                                    @if ($routeName)
                                        {{-- Parent juga punya link sendiri --}}
                                        <a class="collapse-item {{ request()->routeIs($routeName . '*') ? 'active' : '' }}"
                                            href="{{ route($routeName) }}">
                                            {{ $menu->mNama }}
                                        </a>
                                    @endif
                                    @foreach ($children as $child)
                                        @php
                                            $childRoute = $resolveRoute($child->mRoute);
                                            $grandchildren = $grandchildMenus->get($child->mId, collect());
                                            $childActive = $childRoute && request()->routeIs($childRoute . '*');
                                            // Submenu child terbuka bila salah satu grandchild-nya aktif
                                            $subActive = $grandchildren->contains(function ($gc) use ($resolveRoute) {
                                                $gcRoute = $resolveRoute($gc->mRoute);
                                                return $gcRoute && request()->routeIs($gcRoute . '*');
                                            });
                                        @endphp

                                        @if ($grandchildren->isNotEmpty())
                                            {{-- Child yang punya submenu sendiri (menu level 3) --}}
                                            <a class="collapse-item submenu-toggle {{ $childActive || $subActive ? 'active' : 'collapsed' }}"
                                                href="#" data-toggle="collapse"
                                                data-target="#subMenu{{ $child->mId }}"
                                                aria-expanded="{{ $subActive ? 'true' : 'false' }}"
                                                aria-controls="subMenu{{ $child->mId }}">
                                                {{ $child->mNama }}
                                                <i class="fas fa-chevron-down float-right mt-1 fa-xs"></i>
                                            </a>
                                            <div id="subMenu{{ $child->mId }}" class="collapse {{ $subActive ? 'show' : '' }}">
                                                <div class="bg-light py-2 collapse-inner-nested rounded">
                                                    @foreach ($grandchildren as $gc)
                                                        @php $gcRoute = $resolveRoute($gc->mRoute); @endphp
                                                        <a class="collapse-item {{ $gcRoute && request()->routeIs($gcRoute . '*') ? 'active' : '' }}"
                                                            href="{{ $gcRoute ? route($gcRoute) : '#' }}">
                                                            {{ $gc->mNama }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            <a class="collapse-item {{ $childActive ? 'active' : '' }}"
                                                href="{{ $childRoute ? route($childRoute) : '#' }}">
                                                {{ $child->mNama }}
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </li>
                    @else
                        {{-- Single Link Menu --}}
                        <li class="nav-item {{ $active ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route($routeName) }}">
                                <i class="fas fa-fw {{ $menu->mIcon }}"></i>
                                <span>{{ $menu->mNama }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach

                <hr class="sidebar-divider d-none d-md-block">

                <div class="text-center d-none d-md-inline">
                    <button class="rounded-circle border-0" id="sidebarToggle"></button>
                </div>

            </ul>

            {{-- Mobile drawer backdrop --}}
            <div class="sidebar-backdrop" aria-hidden="true"></div>

            {{-- Content Wrapper --}}
            <div id="content-wrapper" class="d-flex flex-column">

                <div id="content">

                    {{-- Topbar --}}
                    <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                        <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                            <i class="fa fa-bars"></i>
                        </button>

                        <ul class="navbar-nav ml-auto">
                            <div class="topbar-divider d-none d-sm-block"></div>

                            <li class="nav-item dropdown no-arrow">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <span
                                        class="mr-2 d-none d-lg-inline text-gray-600 small">{{ Auth::user()->name }}</span>
                                    <img class="img-profile rounded-circle"
                                        src="{{ url('/') }}/assets/img/user.svg">
                                </a>

                                <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                    aria-labelledby="userDropdown">
                                    <a class="dropdown-item" href="#">
                                        <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                        Profile
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </li>
                        </ul>
                    </nav>

                    {{-- Main Content --}}
                    <div class="container-fluid">
                        {{-- Flash Messages --}}
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ session('error') }}
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        @endif

                        @yield('content')
                    </div>
                </div>

                {{-- Footer --}}
                <footer class="sticky-footer bg-white">
                    <div class="container my-auto">
                        <div class="copyright text-center my-auto">
                            <span>Copyright &copy; KEURIQ {{ date('Y') }}</span>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</body>
<x-template-bottom />
