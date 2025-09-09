const fs = require('fs');
const path = require('path');

// Configuration
const SITE_URL = 'https://yourstore.com';
const OUTPUT_PATH = path.join(__dirname, '../src/sitemap.xml');

// Static routes
const staticRoutes = [
  { url: '/', changefreq: 'daily', priority: '1.0' },
  { url: '/products', changefreq: 'daily', priority: '0.9' },
  { url: '/cart', changefreq: 'weekly', priority: '0.7' },
  { url: '/auth/login', changefreq: 'monthly', priority: '0.5' },
  { url: '/auth/register', changefreq: 'monthly', priority: '0.5' },
  { url: '/account/dashboard', changefreq: 'weekly', priority: '0.6' },
  { url: '/account/profile', changefreq: 'monthly', priority: '0.5' },
  { url: '/account/orders', changefreq: 'weekly', priority: '0.6' },
  { url: '/account/wishlist', changefreq: 'weekly', priority: '0.6' }
];

// Mock product data (in real app, this would come from your API/database)
const mockProducts = [
  { id: '1', slug: 'wireless-headphones', lastmod: '2024-01-15' },
  { id: '2', slug: 'smartphone-case', lastmod: '2024-01-14' },
  { id: '3', slug: 'laptop-stand', lastmod: '2024-01-13' },
  { id: '4', slug: 'bluetooth-speaker', lastmod: '2024-01-12' },
  { id: '5', slug: 'gaming-mouse', lastmod: '2024-01-11' }
];

// Mock categories
const mockCategories = [
  { slug: 'electronics', lastmod: '2024-01-10' },
  { slug: 'accessories', lastmod: '2024-01-09' },
  { slug: 'computers', lastmod: '2024-01-08' },
  { slug: 'audio', lastmod: '2024-01-07' }
];

function generateSitemap() {
  let sitemap = `<?xml version="1.0" encoding="UTF-8"?>
`;
  sitemap += `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
`;

  // Add static routes
  staticRoutes.forEach(route => {
    sitemap += `  <url>
`;
    sitemap += `    <loc>${SITE_URL}${route.url}</loc>
`;
    sitemap += `    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
`;
    sitemap += `    <changefreq>${route.changefreq}</changefreq>
`;
    sitemap += `    <priority>${route.priority}</priority>
`;
    sitemap += `  </url>
`;
  });

  // Add product pages
  mockProducts.forEach(product => {
    sitemap += `  <url>
`;
    sitemap += `    <loc>${SITE_URL}/products/${product.id}</loc>
`;
    sitemap += `    <lastmod>${product.lastmod}</lastmod>
`;
    sitemap += `    <changefreq>weekly</changefreq>
`;
    sitemap += `    <priority>0.8</priority>
`;
    sitemap += `  </url>
`;
  });

  // Add category pages
  mockCategories.forEach(category => {
    sitemap += `  <url>
`;
    sitemap += `    <loc>${SITE_URL}/products?category=${category.slug}</loc>
`;
    sitemap += `    <lastmod>${category.lastmod}</lastmod>
`;
    sitemap += `    <changefreq>daily</changefreq>
`;
    sitemap += `    <priority>0.7</priority>
`;
    sitemap += `  </url>
`;
  });

  sitemap += `</urlset>`;

  return sitemap;
}

function writeSitemap() {
  try {
    const sitemapContent = generateSitemap();
    
    // Ensure the directory exists
    const dir = path.dirname(OUTPUT_PATH);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
    
    fs.writeFileSync(OUTPUT_PATH, sitemapContent, 'utf8');
    console.log(`✅ Sitemap generated successfully at: ${OUTPUT_PATH}`);
    console.log(`📊 Generated ${staticRoutes.length + mockProducts.length + mockCategories.length} URLs`);
  } catch (error) {
    console.error('❌ Error generating sitemap:', error);
    process.exit(1);
  }
}

// Generate robots.txt as well
function generateRobotsTxt() {
  const robotsContent = `User-agent: *
Allow: /

Sitemap: ${SITE_URL}/sitemap.xml
`;
  const robotsPath = path.join(__dirname, '../src/robots.txt');
  
  try {
    fs.writeFileSync(robotsPath, robotsContent, 'utf8');
    console.log(`✅ Robots.txt generated successfully at: ${robotsPath}`);
  } catch (error) {
    console.error('❌ Error generating robots.txt:', error);
  }
}

// Run the script
if (require.main === module) {
  console.log('🚀 Generating SEO files...');
  writeSitemap();
  generateRobotsTxt();
  console.log('✨ SEO files generation completed!');
}

module.exports = { generateSitemap, writeSitemap, generateRobotsTxt };