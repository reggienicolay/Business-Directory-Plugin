/**
 * Business Detail Page - Interactive Features
 * Handles: Photo Lightbox, Social Sharing, Map
 * Updated with blue/teal color scheme and heart marker
 */

(function() {
    'use strict';

    // ========================================================================
    // PHOTO LIGHTBOX
    // ========================================================================
    
    const Lightbox = {
        currentIndex: 0,
        photos: window.bdBusinessPhotos || [],
        
        init: function() {
            if (this.photos.length === 0) return;
            
            // Bind click events to gallery items
            document.querySelectorAll('.bd-gallery-clickable').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const index = parseInt(item.dataset.index) || 0;
                    this.open(index);
                });
            });
            
            // Bind lightbox controls
            const lightbox = document.getElementById('bd-lightbox');
            if (!lightbox) return;
            
            const closeBtn = lightbox.querySelector('.bd-lightbox-close');
            const prevBtn = lightbox.querySelector('.bd-lightbox-prev');
            const nextBtn = lightbox.querySelector('.bd-lightbox-next');
            
            closeBtn?.addEventListener('click', () => this.close());
            prevBtn?.addEventListener('click', () => this.prev());
            nextBtn?.addEventListener('click', () => this.next());
            
            // Close on background click
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) this.close();
            });
            
            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (lightbox.style.display === 'none') return;
                
                if (e.key === 'Escape') this.close();
                if (e.key === 'ArrowLeft') this.prev();
                if (e.key === 'ArrowRight') this.next();
            });
        },
        
        open: function(index) {
            this.currentIndex = index;
            this.render();
            
            const lightbox = document.getElementById('bd-lightbox');
            lightbox.style.display = 'flex';
            setTimeout(() => lightbox.classList.add('active'), 10);
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        },
        
        close: function() {
            const lightbox = document.getElementById('bd-lightbox');
            lightbox.classList.remove('active');
            setTimeout(() => {
                lightbox.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
        },
        
        next: function() {
            this.currentIndex = (this.currentIndex + 1) % this.photos.length;
            this.render();
        },
        
        prev: function() {
            this.currentIndex = (this.currentIndex - 1 + this.photos.length) % this.photos.length;
            this.render();
        },
        
        render: function() {
            const photo = this.photos[this.currentIndex];
            const img = document.getElementById('bd-lightbox-image');
            const counter = document.getElementById('bd-lightbox-counter');
            
            if (img) {
                img.src = photo.url;
                img.alt = photo.alt;
            }
            
            if (counter) {
                counter.textContent = `${this.currentIndex + 1} / ${this.photos.length}`;
            }
        }
    };

    // ========================================================================
    // SOCIAL SHARING
    // ========================================================================
    
    const SocialSharing = {
        init: function() {
            // Copy link button
            const copyBtn = document.querySelector('.bd-share-copy');
            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    const url = this.dataset.url;
                    
                    navigator.clipboard.writeText(url).then(() => {
                        // Visual feedback
                        const originalText = this.innerHTML;
                        this.classList.add('copied');
                        this.innerHTML = `
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                            </svg>
                            Copied!
                        `;
                        
                        setTimeout(() => {
                            this.classList.remove('copied');
                            this.innerHTML = originalText;
                        }, 2000);
                    }).catch(err => {
                        console.error('Failed to copy:', err);
                    });
                });
            }
        }
    };

    // ========================================================================
    // MAP INITIALIZATION
    // ========================================================================
    
    const BusinessMap = {
        map: null,
        
        init: function() {
            // Support both old and new map element IDs
            const mapEl = document.getElementById('bd-sidebar-map') || document.getElementById('bd-location-map');
            if (!mapEl || typeof L === 'undefined') return;
            
            const lat = parseFloat(mapEl.dataset.lat);
            const lng = parseFloat(mapEl.dataset.lng);
            
            if (!lat || !lng) return;
            
            // Initialize map using the element's actual ID
            this.map = L.map(mapEl.id, {
                center: [lat, lng],
                zoom: 15,
                scrollWheelZoom: false,
                zoomControl: true
            });
            
            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(this.map);
            
            // Add custom marker - Heart icon matching site branding
            // Inline styles ensure it works even if map-markers.css isn't loaded
            const customIcon = L.divIcon({
                className: 'bd-detail-heart-icon',
                html: `<div style="
                    width: 32px;
                    height: 32px;
                    color: #0F2A43;
                    filter: drop-shadow(0 2px 4px rgba(15, 42, 67, 0.3));
                    cursor: pointer;
                ">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%;">
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                    </svg>
                </div>`,
                iconSize: [32, 32],
                iconAnchor: [16, 32]
            });
            
            // Add inline styles to prevent Leaflet default marker backgrounds
            if (!document.getElementById('bd-heart-icon-styles')) {
                const style = document.createElement('style');
                style.id = 'bd-heart-icon-styles';
                style.textContent = '.bd-detail-heart-icon { background: transparent !important; border: none !important; }';
                document.head.appendChild(style);
            }
            
            L.marker([lat, lng], { icon: customIcon }).addTo(this.map);
        }
    };

    // ========================================================================
    // SCROLL ANIMATIONS (Optional Enhancement)
    // ========================================================================
    
    const ScrollAnimations = {
        init: function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Animate cards on scroll - exclude about section
            document.querySelectorAll('.bd-info-card:not(.bd-about-section), .bd-similar-card').forEach(card => {   
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        }
    };

    // ========================================================================
    // BACK BUTTON HANDLER (from your existing code)
    // ========================================================================
    
    const BackButton = {
        init: function() {
            // Remove old template back button if it exists
            const oldBackBtn = document.querySelector('.bd-back-to-directory');
            if (oldBackBtn) oldBackBtn.remove();
            
            // Smart back navigation - go back if came from directory
            const backLink = document.querySelector('.bd-back-link');
            if (backLink) {
                backLink.addEventListener('click', function(e) {
                    if (document.referrer && document.referrer.includes('/business-directory')) {
                        e.preventDefault();
                        window.history.back();
                    }
                });
            }
        }
    };

    // ========================================================================
    // INITIALIZE EVERYTHING
    // ========================================================================
    
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        BackButton.init();
        Lightbox.init();
        SocialSharing.init();
        
        // Initialize map only if Leaflet is loaded
        if (typeof L !== 'undefined') {
            BusinessMap.init();
        } else {
            // Wait for Leaflet to load (it might be loaded async)
            const checkLeaflet = setInterval(() => {
                if (typeof L !== 'undefined') {
                    clearInterval(checkLeaflet);
                    BusinessMap.init();
                }
            }, 100);
            
            // Stop checking after 5 seconds
            setTimeout(() => clearInterval(checkLeaflet), 5000);
        }
        
        // Optional: Scroll animations
        if ('IntersectionObserver' in window) {
            ScrollAnimations.init();
        }
    }
    
    // Start initialization
    init();
    
})();
