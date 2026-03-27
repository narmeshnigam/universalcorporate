<?php
/**
 * Reusable Enquiry Form Component
 * Can be included on any page across the website
 *
 * Usage:
 *   $enquiryHeading = 'Your Custom Heading';
 *   $enquiryMessage = 'Your custom message here.';
 *   include 'includes/enquiry-form.php';
 */

// Configurable CTA content — set these before including this file
$ctaHeading = $enquiryHeading ?? 'Get in Touch';
$ctaMessage = $enquiryMessage ?? 'Have a question or want to work with us? We\'d love to hear from you.';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$formId = 'enquiry-form-' . uniqid();
?>

<div class="enquiry-form-container">
    <div class="enquiry-form-wrapper">
        <div class="enquiry-cta">
            <h2><?php echo htmlspecialchars($ctaHeading); ?></h2>
            <p><?php echo htmlspecialchars($ctaMessage); ?></p>
            <div class="cta-features">
                <div class="cta-feature">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <span>Quick Response</span>
                </div>
                <div class="cta-feature">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>24/7 Support</span>
                </div>
                <div class="cta-feature">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Expert Team</span>
                </div>
            </div>
        </div>
        
        <div class="enquiry-form-section">
            <form id="<?php echo $formId; ?>" class="enquiry-form" method="POST" action="api/submit-enquiry.php">
                <input type="hidden" name="page_name" value="<?php echo htmlspecialchars($currentPage); ?>">
                
                <div class="form-response" id="form-response-<?php echo $formId; ?>" style="display: none;"></div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="name-<?php echo $formId; ?>">Full Name <span class="required">*</span></label>
                        <input type="text" id="name-<?php echo $formId; ?>" name="name" required placeholder="John Doe">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="email-<?php echo $formId; ?>">Email Address <span class="required">*</span></label>
                        <input type="email" id="email-<?php echo $formId; ?>" name="email" required placeholder="john@example.com">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="phone-<?php echo $formId; ?>">Phone Number</label>
                        <input type="tel" id="phone-<?php echo $formId; ?>" name="phone" placeholder="+1 (555) 123-4567">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="subject-<?php echo $formId; ?>">Subject</label>
                        <input type="text" id="subject-<?php echo $formId; ?>" name="subject" placeholder="How can we help you?">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="message-<?php echo $formId; ?>">Message <span class="required">*</span></label>
                        <textarea id="message-<?php echo $formId; ?>" name="message" rows="5" required placeholder="Tell us more about your enquiry..."></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <button type="submit" class="btn-submit">
                        <span class="btn-text">Send Enquiry</span>
                        <span class="btn-loader" style="display: none;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" opacity="0.25"></circle>
                                <path d="M12 2 A10 10 0 0 1 22 12" stroke-linecap="round"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('<?php echo $formId; ?>');
    const responseDiv = document.getElementById('form-response-<?php echo $formId; ?>');
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('.btn-submit');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoader = submitBtn.querySelector('.btn-loader');
        
        // Disable button and show loader
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoader.style.display = 'inline-block';
        responseDiv.style.display = 'none';
        
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            // Show response message
            responseDiv.style.display = 'block';
            responseDiv.className = 'form-response ' + (result.success ? 'success' : 'error');
            responseDiv.textContent = result.message;
            
            if (result.success) {
                form.reset();
            }
            
        } catch (error) {
            responseDiv.style.display = 'block';
            responseDiv.className = 'form-response error';
            responseDiv.textContent = 'An error occurred. Please try again later.';
        } finally {
            // Re-enable button and hide loader
            submitBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
        }
    });
});
</script>
