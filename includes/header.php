<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? ($site['site_name'] ?? 'Universal Corporate'); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <a href="index.php" class="logo">
                <img src="<?php echo htmlspecialchars($site['logo_path'] ?? 'assets/branding/default_logo.png'); ?>" alt="<?php echo htmlspecialchars($site['site_name'] ?? 'Logo'); ?>" class="logo-icon">
                <span><?php echo htmlspecialchars($site['site_name'] ?? 'Universal Corporate'); ?></span>
            </a>
            <button class="hamburger" id="hamburger" aria-label="Toggle navigation" aria-expanded="false">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            <ul class="nav-links" id="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
        </nav>
    </header>
    <main>

<script>
document.getElementById('hamburger').addEventListener('click', function() {
    const nav = document.getElementById('nav-links');
    const open = nav.classList.toggle('active');
    this.classList.toggle('active');
    this.setAttribute('aria-expanded', open);
});

document.querySelectorAll('#nav-links a').forEach(function(link) {
    link.addEventListener('click', function() {
        document.getElementById('nav-links').classList.remove('active');
        document.getElementById('hamburger').classList.remove('active');
        document.getElementById('hamburger').setAttribute('aria-expanded', 'false');
    });
});
</script>
