@php
    $groups = \App\Support\NavigationMenu::visibleGroups(auth()->user());
@endphp

<ul class="navbar-nav topbar-nav flex-lg-row flex-wrap gap-lg-1 me-lg-auto">
    @foreach($groups as $group)
        @if($group['type'] === 'link')
            <li class="nav-item">
                <a class="nav-link {{ $group['active'] ? 'active' : '' }}" href="{{ route($group['route']) }}">
                    <i class="bi {{ $group['icon'] }}"></i><span>{{ $group['label'] }}</span>
                </a>
            </li>
        @else
            <li class="nav-item dropdown">
                <a
                    class="nav-link dropdown-toggle {{ $group['active'] ? 'active' : '' }}"
                    href="#"
                    role="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    {{ $group['label'] }}
                </a>
                <ul class="dropdown-menu">
                    @foreach($group['items'] as $item)
                        <li>
                            <a class="dropdown-item {{ $item['active'] ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                <i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endif
    @endforeach
</ul>
