<nav class="navbar header">
    
    <i id="btn-menu" class="material-icons">menu</i>

    <!-- Branding Image -->    
    <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name', '') }}</a>
    <ul class="navbar-nav mr-auto" style="height: 40px;">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-toggle="dropdown" id="navbarDropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                {{ $version }} <span class="caret"></span>
            </a>
            <div class="dropdown-menu dropdown-menu-version" aria-labelledby="navbarDropdown">
                <a href="#" class="dropdown-item">v1</a>
                <a href="#" class="dropdown-item">v2</a>            
            </div>
        </li>
    </ul>    
    <!-- Right Side Of Navbar -->
    <ul class="nav">
        <!-- Authentication Links -->
        @if (Auth::guest())
            <li class="nav-item">
                <a href="{{ route('login') }}" class="nav-link">Login</a>
            </li>
        @else
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                    {{ Auth::user()->username }} <span class="caret"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('logout-form').submit();">Logout</a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">{{ csrf_field() }}</form>
                </div>
            </li>
        @endif
    </ul>
</nav>