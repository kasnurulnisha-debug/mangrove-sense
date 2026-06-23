# 🌳 MangroveSense — Blue Carbon Monitoring Dashboard

**🌐 Live Demo**: [mangrove-sense.infinityfree.me](http://mangrove-sense.infinityfree.me)  
**💻 Source Code**: [github.com/kasnurulnisha-debug/mangrove-sense](https://github.com/kasnurulnisha-debug/mangrove-sense)

A web-based GIS application for monitoring mangrove trees and tracking blue carbon sequestration in Iskandar Puteri, Malaysia.

## 📋 Overview

MangroveSense is a comprehensive tree inventory and carbon analytics platform designed for researchers and conservationists working with blue carbon ecosystems. The system enables manual tree data entry, spatial visualization, and automated carbon stock calculations using IPCC Tier 1 methodologies.

## ✨ Features

### 🏠 Dashboard Home (index.html)
- **Manual Tree Entry Form** — Record tree measurements with photo documentation
- **Real-time Statistics** — Total trees, biomass, carbon stock, and economic value
- **Recently Added Entries** — View latest manual tree records
- **Plot Overview Map** — Quick visualization of tree locations

### 🗺️ Plot Overview Map (map.html)
- **Interactive Map** — Leaflet.js-based map showing all tree locations
- **Health Status Indicators** — Color-coded markers (Healthy/Monitor/Critical)
- **WGS84 Coordinates** — Accurate GPS positioning for all trees
- **Clustered View** — Handle large datasets efficiently

### 📋 Tree Inventory (inventory.html)
- **Paginated Database** — Browse all recorded trees (25 per page)
- **Advanced Search** — Filter by tree code, zone, species, or health status
- **Detailed View** — See all measurements and metadata
- **Export Ready** — Structured data for research analysis

### 📊 Analytics Carbon (analytics.html)
- **Carbon Stock Calculations** — Automated AGB and carbon sequestration estimates
- **Species Composition** — Distribution charts by mangrove species
- **Health Status Analysis** — Tree condition monitoring
- **DBH Distribution** — Size class analysis
- **Temporal Trends** — Cumulative tree count over time
- **Economic Valuation** — Carbon credit value estimation (USD 18/tCO₂)

## 🌐 Access Information

**Production URL**: [http://mangrove-sense.infinityfree.me](http://mangrove-sense.infinityfree.me)

**Pages**:
- **Dashboard**: [http://mangrove-sense.infinityfree.me/index.html](http://mangrove-sense.infinityfree.me/index.html)
- **Map**: [http://mangrove-sense.infinityfree.me/map.html](http://mangrove-sense.infinityfree.me/map.html)
- **Inventory**: [http://mangrove-sense.infinityfree.me/inventory.html](http://mangrove-sense.infinityfree.me/inventory.html)
- **Analytics**: [http://mangrove-sense.infinityfree.me/analytics.html](http://mangrove-sense.infinityfree.me/analytics.html)

## 🛠️ Technology Stack

**Frontend:**
- HTML5 + CSS3 (Responsive design)
- Vanilla JavaScript (No frameworks)
- Chart.js (Analytics visualization)
- Leaflet.js (Interactive maps)

**Backend:**
- PHP 7.4+ (API endpoints)
- MySQL 5.7+ / MariaDB 10.3+ (Database)
- PDO (Secure database access)

**Hosting & Version Control:**
- **Live Hosting**: InfinityFree (mangrove-sense.infinityfree.me)
- **Source Code**: GitHub (kasnurulnisha-debug/mangrove-sense)
- **Local Development**: XAMPP

## 📁 Project Folder Structure

```text
mangrove-sense/
├── index.html                    # Main dashboard & tree entry form
├── map.html                      # Interactive tree location map
├── inventory.html                # Paginated tree inventory list
├── analytics.html                # Carbon analytics & charts
├── api.php                       # Backend API endpoints
├── mangrove_sense.sql            # Database schema + 479 initial records
├── README.md                     # This file
├── .gitignore                    # Git ignore rules
│
├── images/                       # Static images and assets
│   ├── logo.png
│   ├── dashboard-bg.jpg
│   └── ... (other static assets)
│
└── uploads/                      # User-uploaded tree photos (auto-generated)
    ├── BLUECARBONP0001_20260623_143022.jpg
    └── ... 
