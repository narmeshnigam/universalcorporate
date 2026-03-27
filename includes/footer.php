    </main>
    <footer class="site-footer">
        <div class="footer-main">
            <div class="footer-grid">
                <div class="footer-col footer-about">
                    <a href="index.php" class="footer-logo">
                        <img src="<?php echo htmlspecialchars($site['logo_path'] ?? 'assets/branding/default_logo.png'); ?>" alt="<?php echo htmlspecialchars($site['site_name'] ?? 'Logo'); ?>" class="footer-logo-img">
                        <span><?php echo htmlspecialchars($site['site_name'] ?? 'Universal Corporate'); ?></span>
                    </a>
                    <p><?php echo htmlspecialchars($site['site_description'] ?? ''); ?></p>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Our Services</h4>
                    <ul>
                        <li>Office Supplies</li>
                        <li>Cleaning Products</li>
                        <li>Pantry Essentials</li>
                        <li>School Supplies</li>
                        <li>Housekeeping Items</li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact Info</h4>
                    <ul class="footer-contact">
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <span><?php echo htmlspecialchars($site['address'] ?? ''); ?></span>
                        </li>
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            <a href="tel:+<?php echo htmlspecialchars($site['phone_raw'] ?? ''); ?>"><?php echo htmlspecialchars($site['phone'] ?? ''); ?></a>
                        </li>
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <a href="mailto:<?php echo htmlspecialchars($site['email'] ?? ''); ?>"><?php echo htmlspecialchars($site['email'] ?? ''); ?></a>
                        </li>
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span><?php echo htmlspecialchars($site['working_hours'] ?? ''); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site['site_name'] ?? 'Universal Corporate'); ?>. All rights reserved.</p>
        </div>
    </footer>

    <!-- Sticky Action Buttons -->
    <a href="tel:+<?php echo htmlspecialchars($site['phone_raw'] ?? '1234567890'); ?>" class="sticky-float call-float" aria-label="Call us">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="white">
            <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 00-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
        </svg>
    </a>

    <a href="https://wa.me/<?php echo htmlspecialchars($site['whatsapp'] ?? '1234567890'); ?>" target="_blank" rel="noopener noreferrer" class="sticky-float whatsapp-float" aria-label="Chat on WhatsApp">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="white">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    </a>

    <!-- Hero Slider -->
    <script src="js/slider.js"></script>

    <!-- Enquiry Modal -->
    <script>
    // FAQ Accordion
    document.querySelectorAll('.faq-question').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var item = this.parentElement;
            var isOpen = item.classList.contains('open');
            // Close all
            document.querySelectorAll('.faq-item.open').forEach(function(el) { el.classList.remove('open'); el.querySelector('.faq-question').setAttribute('aria-expanded', 'false'); });
            // Toggle clicked
            if (!isOpen) { item.classList.add('open'); this.setAttribute('aria-expanded', 'true'); }
        });
    });
    </script>

    <!-- Enquiry Modal -->
    <script>
    (function() {
        const modal = document.getElementById('enquiryModal');
        const modalTitle = document.getElementById('modalServiceTitle');
        const modalSubject = document.getElementById('modalSubject');
        const closeBtn = document.getElementById('modalClose');
        const serviceCards = document.querySelectorAll('.service-card-img');
        const form = document.getElementById('modalEnquiryForm');
        const responseDiv = document.getElementById('modalFormResponse');

        function openModal(serviceName) {
            modalTitle.textContent = 'Enquire About ' + serviceName;
            modalSubject.value = serviceName;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            responseDiv.style.display = 'none';
        }

        serviceCards.forEach(function(card) {
            card.addEventListener('click', function(e) {
                e.preventDefault();
                openModal(this.dataset.service || 'Our Service');
            });
        });

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
        });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = form.querySelector('.modal-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
            responseDiv.style.display = 'none';

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, { method: 'POST', body: formData });
                const result = await response.json();
                
                responseDiv.style.display = 'block';
                responseDiv.className = 'form-response ' + (result.success ? 'success' : 'error');
                responseDiv.textContent = result.message;
                
                if (result.success) form.reset();
            } catch (error) {
                responseDiv.style.display = 'block';
                responseDiv.className = 'form-response error';
                responseDiv.textContent = 'An error occurred. Please try again.';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Enquiry';
            }
        });
    })();
    </script>

</body>
</html>
