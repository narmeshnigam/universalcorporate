<?php
$pageTitle = 'Universal Corporate';
require_once 'config/database.php';
require_once 'config/identity.php';
$pdo = getDatabaseConnection();
$site = getSiteIdentity($pdo);
$pageTitle = $site['site_name'];

$heroSlides = [];
$services = [];
$clients = [];
$brands = [];

if ($pdo) {
    try { $heroSlides = $pdo->query("SELECT * FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(); } catch (Exception $e) { $heroSlides = []; }
    try { $services = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(); } catch (Exception $e) { $services = []; }
    try { $clients = $pdo->query("SELECT * FROM clients WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(); } catch (Exception $e) { $clients = []; }
    try { $brands = $pdo->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(); } catch (Exception $e) { $brands = []; }
    try { $ctaBanner = $pdo->query("SELECT * FROM cta_banners WHERE is_active = 1 LIMIT 1")->fetch(); } catch (Exception $e) { $ctaBanner = null; }
    try { $faqs = $pdo->query("SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(); } catch (Exception $e) { $faqs = []; }
}

include 'includes/header.php';
?>

        <section id="home" class="hero-slider" aria-label="Hero slideshow">
            <?php if (!empty($heroSlides)): ?>
                <div class="slider-track">
                    <?php foreach ($heroSlides as $i => $slide): ?>
                    <div class="slide <?php echo $i === 0 ? 'active' : ''; ?>" aria-hidden="<?php echo $i === 0 ? 'false' : 'true'; ?>">
                        <picture>
                            <?php if (!empty($slide['image_path_mobile'])): ?>
                            <source media="(max-width: 768px)" srcset="<?php echo htmlspecialchars($slide['image_path_mobile']); ?>">
                            <?php endif; ?>
                            <img src="<?php echo htmlspecialchars($slide['image_path']); ?>" alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide ' . ($i + 1)); ?>" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>">
                        </picture>
                        <?php if ($slide['title'] || $slide['subtitle']): ?>
                        <div class="slide-overlay">
                            <?php if ($slide['title']): ?>
                                <h1><?php echo htmlspecialchars($slide['title']); ?></h1>
                            <?php endif; ?>
                            <?php if ($slide['subtitle']): ?>
                                <p><?php echo htmlspecialchars($slide['subtitle']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($heroSlides) > 1): ?>
                <button class="slider-btn slider-prev" aria-label="Previous slide">&#10094;</button>
                <button class="slider-btn slider-next" aria-label="Next slide">&#10095;</button>
                <div class="slider-dots" role="tablist">
                    <?php foreach ($heroSlides as $i => $slide): ?>
                    <button class="dot <?php echo $i === 0 ? 'active' : ''; ?>" role="tab" aria-label="Go to slide <?php echo $i + 1; ?>" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" data-index="<?php echo $i; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="slide active hero-fallback">
                    <div class="slide-overlay">
                        <h1>Welcome to <?php echo htmlspecialchars($site['site_name']); ?></h1>
                        <p><?php echo htmlspecialchars($site['site_tagline']); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="features-strip">
            <div class="features-strip-inner">
                <div class="feature-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12M6 8h12M6 3v5M18 3v5M8 8v13l4-3 4 3V8M8 8c0-1 .5-2 2-2h4c1.5 0 2 1 2 2"></path></svg>
                    <span>Affordable Price</span>
                </div>
                <div class="feature-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Large Variety</span>
                </div>
                <div class="feature-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                    <span>Premium Quality</span>
                </div>
                <div class="feature-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
                    <span>Great Discounts</span>
                </div>
                <div class="feature-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    <span>Timely Delivery</span>
                </div>
                <div class="feature-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                    <span>Easy Returns</span>
                </div>
                <!-- Duplicate for seamless marquee loop -->
                <div class="feature-item" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12M6 8h12M6 3v5M18 3v5M8 8v13l4-3 4 3V8M8 8c0-1 .5-2 2-2h4c1.5 0 2 1 2 2"></path></svg>
                    <span>Affordable Price</span>
                </div>
                <div class="feature-item" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Large Variety</span>
                </div>
                <div class="feature-item" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                    <span>Premium Quality</span>
                </div>
                <div class="feature-item" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
                    <span>Great Discounts</span>
                </div>
                <div class="feature-item" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    <span>Timely Delivery</span>
                </div>
                <div class="feature-item" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                    <span>Easy Returns</span>
                </div>
            </div>
        </section>

        <section id="about" class="about-section">
            <div class="about-container">
                <div class="about-content">
                    <h2>Your Trusted Partner for Daily Supplies</h2>
                    <p>We provide reliable delivery of essential supplies for offices, schools, and housekeeping needs. From stationery and cleaning products to pantry essentials, we ensure your workplace runs smoothly with timely doorstep delivery and competitive pricing.</p>
                </div>
            </div>
        </section>

        <section id="services" class="services-section">
            <h2>Our Services</h2>
            <div class="services-grid-2col">
                <?php if (!empty($services)): ?>
                    <?php foreach ($services as $service): ?>
                    <a href="#" class="service-card-img" style="background-image: url('<?php echo htmlspecialchars($service['image_path']); ?>');" data-service="<?php echo htmlspecialchars($service['title']); ?>">
                        <div class="service-text">
                            <span class="service-label"><?php echo htmlspecialchars($service['title']); ?></span>
                            <?php if ($service['subtitle']): ?>
                            <span class="service-subtitle"><?php echo htmlspecialchars($service['subtitle']); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-services">No services available at the moment.</p>
                <?php endif; ?>
            </div>
        </section>

        <section id="why-us" class="why-us-section">
            <h2>Why Choose Us</h2>
            <div class="why-us-grid">
                <div class="why-us-card">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    <h3>Fast Delivery</h3>
                    <p>Same-day and next-day delivery options to keep your operations running without interruption.</p>
                </div>
                <div class="why-us-card">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                    <h3>Quality Assured</h3>
                    <p>We source products from trusted brands ensuring you receive only the best quality supplies.</p>
                </div>
                <div class="why-us-card">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    <h3>Competitive Pricing</h3>
                    <p>Bulk discounts and wholesale rates that help you save more on regular purchases.</p>
                </div>
                <div class="why-us-card">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    <h3>Dedicated Support</h3>
                    <p>Our team is always ready to assist with orders, queries, and custom requirements.</p>
                </div>
            </div>
        </section>

        <section class="clients-section">
            <h2>Our Customers</h2>
            <p class="section-subtitle">Trusted by businesses, schools, and institutions across the region</p>
            <div class="clients-strip">
                <div class="clients-track">
                    <?php if (!empty($clients)): ?>
                        <?php foreach ($clients as $client): ?>
                        <img src="<?php echo htmlspecialchars($client['logo_path']); ?>" alt="<?php echo htmlspecialchars($client['client_name']); ?>">
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-clients">No clients to display yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="brands-section">
            <h2>Brands We Deal In</h2>
            <p class="section-subtitle">Partnered with leading brands to bring you quality products</p>
            <div class="clients-strip">
                <div class="clients-track">
                    <?php if (!empty($brands)): ?>
                        <?php foreach ($brands as $brand): ?>
                        <img src="<?php echo htmlspecialchars($brand['logo_path']); ?>" alt="<?php echo htmlspecialchars($brand['brand_name']); ?>">
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-clients">No brands to display yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="partner-section">
            <div class="partner-grid">
                <div class="partner-card">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <h3>Partner With Us</h3>
                    <p>We are always looking for reliable partners to expand our supply network. Whether you are a manufacturer, distributor, or retailer, we offer transparent terms and long-term collaboration opportunities.</p>
                    <a href="#contact" class="partner-link">Get in touch</a>
                </div>
                <div class="partner-divider"></div>
                <div class="partner-card">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
                    <h3>Bulk Orders</h3>
                    <p>Need supplies in large quantities? We offer special pricing and dedicated support for bulk orders. From offices to institutions, we handle orders of any scale with timely delivery guaranteed.</p>
                    <a href="#contact" class="partner-link">Request a quote</a>
                </div>
            </div>
        </section>

        <?php if ($ctaBanner): ?>
        <div class="cta-banner-wrapper">
            <div class="cta-banner" style="background-image: url('<?php echo htmlspecialchars($ctaBanner['image_path']); ?>');">
                <div class="cta-banner-content">
                    <?php if ($ctaBanner['heading']): ?>
                        <h2><?php echo htmlspecialchars($ctaBanner['heading']); ?></h2>
                    <?php endif; ?>
                    <?php if ($ctaBanner['subheading']): ?>
                        <p><?php echo htmlspecialchars($ctaBanner['subheading']); ?></p>
                    <?php endif; ?>
                    <?php if ($ctaBanner['button_text']): ?>
                        <a href="<?php echo htmlspecialchars($ctaBanner['button_link'] ?? '#contact'); ?>" class="cta-btn"><?php echo htmlspecialchars($ctaBanner['button_text']); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Enquiry Modal -->
        <div class="enquiry-modal" id="enquiryModal">
            <div class="enquiry-modal-content">
                <button class="modal-close" id="modalClose" aria-label="Close">&times;</button>
                <h3 id="modalServiceTitle">Enquire About Service</h3>
                <form id="modalEnquiryForm" class="modal-form" method="POST" action="api/submit-enquiry.php">
                    <input type="hidden" name="page_name" value="services">
                    <input type="hidden" name="subject" id="modalSubject" value="">
                    <div class="form-response" id="modalFormResponse" style="display: none;"></div>
                    <div class="modal-form-field">
                        <label for="modalName">Full Name <span class="required">*</span></label>
                        <input type="text" id="modalName" name="name" required placeholder="Your name">
                    </div>
                    <div class="modal-form-field">
                        <label for="modalEmail">Email <span class="required">*</span></label>
                        <input type="email" id="modalEmail" name="email" required placeholder="your@email.com">
                    </div>
                    <div class="modal-form-field">
                        <label for="modalPhone">Phone</label>
                        <input type="tel" id="modalPhone" name="phone" placeholder="Your phone number">
                    </div>
                    <div class="modal-form-field">
                        <label for="modalMessage">Message <span class="required">*</span></label>
                        <textarea id="modalMessage" name="message" rows="4" required placeholder="Tell us about your requirements..."></textarea>
                    </div>
                    <button type="submit" class="modal-submit">Send Enquiry</button>
                </form>
            </div>
        </div>

        <?php if (!empty($faqs)): ?>
        <section class="faq-section">
            <h2>Frequently Asked Questions</h2>
            <p class="section-subtitle">Got questions? We have answers</p>
            <div class="faq-list">
                <?php foreach ($faqs as $faq): ?>
                <div class="faq-item">
                    <button class="faq-question" aria-expanded="false">
                        <span><?php echo htmlspecialchars($faq['question']); ?></span>
                        <svg class="faq-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>
                    <div class="faq-answer">
                        <p><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section id="contact" class="section">
            <?php
            $enquiryHeading = 'Get a Quote';
            $enquiryMessage = 'Need supplies for your office, school, or institution? Share your requirements and we\'ll get back to you with the best prices.';
            include 'includes/enquiry-form.php';
            ?>
        </section>

<?php include 'includes/footer.php'; ?>
