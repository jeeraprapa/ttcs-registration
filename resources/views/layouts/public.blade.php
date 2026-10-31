<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Hong Kong Private Preview') | Upper House
        Residences</title>@include('components.favicon')@vite(['resources/css/app.css','resources/js/app.js'])
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0], j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src= 'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f); })(window,document,'script','dataLayer','GTM-MWCHZ48T');</script>
    <!-- End Google Tag Manager -->
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
<!-- Google Tag Manager (noscript) --> <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-MWCHZ48T" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript> <!-- End Google Tag Manager (noscript) -->
</body>
</html>
