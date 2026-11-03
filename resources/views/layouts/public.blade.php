<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Hong Kong Private Preview') | Upper House
        Residences</title>@include('components.favicon')@vite(['resources/css/app.css','resources/js/app.js'])

</head>
<body>
<header class="brand-bar">
    <a href="{{ route('registration.create') }}"
                             aria-label="Home"><img class="brand-logo"
                                                    src="{{ asset('assets/logo.png') }}"
                                                    alt="Upper House Residences"></a>
</header>
<main class="public-main">
    <img class="hero" src="{{ asset('assets/residences.jpg') }}"
                               alt="Upper House Residences overlooking Bangkok">
    @yield('content')
</main>
<footer class="public-footer"
        style="--footer-background: url('{{ asset('assets/bg_footer.png') }}')">
    <div class="footer-links"><a href="https://www.uhresidencesbangkok.com/"
                                 target="_blank" rel="noopener noreferrer">www.uhresidencesbangkok.com</a>
        <div>
            <div class="eyebrow">Stay connected</div>
            <div class="social-links"><a
                    href="https://www.facebook.com/uhresidencesbangkok"
                    aria-label="Facebook" target="_blank" rel="noopener noreferrer"><i
                        class="bi bi-facebook" aria-hidden="true"></i></a><a
                    href="https://www.instagram.com/uhresidencesbangkok/"
                    aria-label="Instagram" target="_blank" rel="noopener noreferrer"><i
                        class="bi bi-instagram" aria-hidden="true"></i></a><a
                    href="https://www.youtube.com/@UHResidencesBangkok"
                    aria-label="YouTube" target="_blank" rel="noopener noreferrer"><i
                        class="bi bi-youtube" aria-hidden="true"></i></a></div>
        </div>
    </div>
</footer>
<script async src="https://www.googletagmanager.com/gtm.js?id=GTM-58VV7QWT"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
</script>
</body>
</html>
