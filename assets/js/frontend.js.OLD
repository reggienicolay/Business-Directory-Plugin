/**
 * Business Directory - Frontend
 */

(function() {
    'use strict';
    
    let map = null;
    let markers = [];
    
    function initDirectory() {
        const mapEl = document.getElementById('bd-map');
        if (!mapEl) return;
        
        // Initialize Leaflet
        map = L.map('bd-map').setView([30.2672, -97.7431], 12);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Load businesses
        loadBusinesses();
        
        // View toggle
        document.querySelectorAll('.bd-view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                switchView(this.dataset.view);
            });
        });
    }
    
    function loadBusinesses() {
        fetch('/wp-json/bd/v1/businesses')
            .then(r => r.json())
            .then(data => {
                renderMarkers(data.data);
                renderList(data.data);
            })
            .catch(err => console.error('Error loading businesses:', err));
    }
    
    function renderMarkers(businesses) {
        businesses.forEach(business => {
            if (business.location) {
                const marker = L.marker([business.location.lat, business.location.lng])
                    .bindPopup(`
                        <strong>${business.title}</strong><br>
                        ${business.location.address}<br>
                        <a href="${business.permalink}">View Details</a>
                    `)
                    .addTo(map);
                
                markers.push(marker);
            }
        });
    }
    
    function renderList(businesses) {
        const container = document.getElementById('bd-list-container');
        
        if (businesses.length === 0) {
            container.innerHTML = '<p>No businesses found.</p>';
            return;
        }
        
        let html = '<div class="bd-business-grid">';
        
        businesses.forEach(business => {
            html += `
                <article class="bd-business-card">
                    ${business.thumbnail ? `<img src="${business.thumbnail}" alt="${business.title}">` : ''}
                    <h3>${business.title}</h3>
                    ${business.excerpt ? `<p>${business.excerpt}</p>` : ''}
                    <div class="bd-business-meta">
                        ${business.price_level ? `<span class="price">${business.price_level}</span>` : ''}
                        ${business.categories.length ? `<span class="category">${business.categories[0]}</span>` : ''}
                    </div>
                    <a href="${business.permalink}" class="bd-btn">View Details</a>
                </article>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    function switchView(view) {
        document.querySelectorAll('.bd-view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });
        
        if (view === 'map') {
            document.getElementById('bd-map-container').style.display = 'block';
            document.getElementById('bd-list-container').style.display = 'none';
            if (map) map.invalidateSize();
        } else {
            document.getElementById('bd-map-container').style.display = 'none';
            document.getElementById('bd-list-container').style.display = 'block';
        }
    }
    
    // Load Leaflet if not already loaded
    if (typeof L === 'undefined') {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(css);
        
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = initDirectory;
        document.body.appendChild(script);
    } else {
        initDirectory();
    }
    
})();
