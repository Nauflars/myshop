<?php

namespace App\Infrastructure\DataFixtures;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $products = [
            // Electronics & Computers
            [
                'name' => 'UltraBook Pro 15.6" Laptop',
                'description' => 'Premium lightweight laptop designed for professionals and content creators. Features Intel Core i7 processor, 16GB DDR4 RAM, 512GB NVMe SSD for lightning-fast performance. Stunning 15.6-inch 4K display with 100% sRGB color accuracy. Perfect for video editing, graphic design, programming, and multitasking. All-day battery life up to 12 hours. Backlit keyboard, fingerprint reader, and Thunderbolt 4 ports. Ideal for remote work, students, and digital nomads.',
                'price' => 1299.99,
                'stock' => 25,
                'category' => 'Electronics'
            ],
            [
                'name' => 'Wireless Noise-Cancelling Headphones',
                'description' => 'Premium over-ear headphones with active noise cancellation technology. Block out distractions for immersive listening experience. 40mm drivers deliver rich bass and crystal-clear highs. Comfortable memory foam ear cushions for extended wear. 30-hour battery life with fast charging. Built-in microphone for calls and voice assistants. Foldable design with carrying case. Perfect for travel, commuting, working from home, or enjoying music and podcasts in peace.',
                'price' => 249.99,
                'stock' => 80,
                'category' => 'Electronics'
            ],
            [
                'name' => 'Ergonomic Wireless Mouse',
                'description' => 'Scientifically designed ergonomic mouse that reduces wrist strain and carpal tunnel risk. Vertical grip promotes natural hand position. Silent click buttons won\'t disturb others. Adjustable 2400 DPI for precision. Rechargeable battery lasts up to 3 months. Works on any surface. Ideal for office workers, gamers, designers, and anyone spending long hours at computer. Compatible with Windows, Mac, and Linux.',
                'price' => 39.99,
                'stock' => 150,
                'category' => 'Electronics'
            ],
            [
                'name' => 'Mechanical RGB Gaming Keyboard',
                'description' => 'Professional mechanical keyboard with customizable RGB backlighting and programmable keys. Cherry MX Brown switches provide tactile feedback perfect for typing and gaming. Anti-ghosting technology ensures every keystroke is registered. Durable aluminum frame. Includes wrist rest for comfort during marathon sessions. Ideal for gamers, programmers, writers, and anyone who values typing quality. USB pass-through and dedicated media controls.',
                'price' => 129.99,
                'stock' => 60,
                'category' => 'Electronics'
            ],
            [
                'name' => '4K Webcam with Ring Light',
                'description' => 'Professional 4K ultra HD webcam with integrated adjustable ring light for perfect video quality. Auto-focus and light correction ensure you always look your best. Built-in dual microphones with noise reduction. 90-degree wide angle lens captures entire workspace. Perfect for video conferencing, streaming, content creation, online teaching, and remote interviews. Works with Zoom, Teams, Skype, OBS. Includes privacy shutter.',
                'price' => 149.99,
                'stock' => 45,
                'category' => 'Electronics'
            ],
            [
                'name' => 'Smart Watch Fitness Tracker',
                'description' => 'Advanced smartwatch with comprehensive health and fitness tracking. Monitors heart rate, blood oxygen, sleep quality, stress levels, and calorie burn. GPS tracking for running, cycling, and hiking. Waterproof design for swimming. Receive calls, messages, and app notifications. 7-day battery life. Perfect for fitness enthusiasts, health-conscious individuals, and busy professionals wanting to stay connected and healthy.',
                'price' => 199.99,
                'stock' => 70,
                'category' => 'Electronics'
            ],

            // Home & Kitchen
            [
                'name' => 'Smart Programmable Coffee Maker',
                'description' => 'WiFi-enabled coffee maker that brews perfect coffee on your schedule. Control via smartphone app or voice assistant. Programmable timer lets you wake up to fresh coffee. Thermal carafe keeps coffee hot for hours. Adjustable strength from mild to bold. 12-cup capacity perfect for families or offices. Self-cleaning function. Ideal for coffee lovers who value convenience and consistency in their morning routine.',
                'price' => 89.99,
                'stock' => 55,
                'category' => 'Home'
            ],
            [
                'name' => 'High-Speed Blender for Smoothies',
                'description' => 'Professional-grade blender with powerful 1500-watt motor crushes ice and frozen fruit effortlessly. Perfect for making nutritious smoothies, protein shakes, soups, nut butters, and sauces. BPA-free 64oz pitcher. 10 variable speeds plus pulse function. Self-cleaning feature. Quiet operation. Ideal for health enthusiasts, athletes, busy parents, and anyone committed to whole-food nutrition and meal prep.',
                'price' => 179.99,
                'stock' => 40,
                'category' => 'Home'
            ],
            [
                'name' => 'Air Purifier with HEPA Filter',
                'description' => 'Advanced air purifier removes 99.97% of airborne particles including dust, pollen, pet dander, mold spores, and smoke. True HEPA filter plus activated carbon. Covers rooms up to 500 sq ft. Quiet sleep mode. Air quality indicator. Perfect for allergy sufferers, pet owners, urban dwellers, and families concerned about clean indoor air. Improves breathing and sleep quality.',
                'price' => 159.99,
                'stock' => 35,
                'category' => 'Home'
            ],
            [
                'name' => 'Memory Foam Pillow Set',
                'description' => 'Therapeutic memory foam pillows designed by chiropractors for proper neck and spine alignment. Reduces neck pain, headaches, and snoring. Temperature-regulating gel layer keeps you cool. Hypoallergenic bamboo cover is soft and breathable. Set of 2 pillows. Perfect for side sleepers, back sleepers, and anyone suffering from neck discomfort. Wake up refreshed and pain-free.',
                'price' => 69.99,
                'stock' => 90,
                'category' => 'Home'
            ],
            [
                'name' => 'LED Desk Lamp with USB Charging',
                'description' => 'Modern LED desk lamp with adjustable brightness and color temperature. Reduces eye strain during work or study. Flexible gooseneck positions light exactly where needed. Built-in USB charging port for phone or tablet. Energy-efficient LEDs last 50,000 hours. Touch controls. Memory function remembers your preferred settings. Perfect for students, office workers, readers, and crafters.',
                'price' => 44.99,
                'stock' => 100,
                'category' => 'Home'
            ],

            // Sports & Outdoors
            [
                'name' => 'Yoga Mat with Alignment Guides',
                'description' => 'Premium non-slip yoga mat with printed alignment lines helps perfect your poses. Extra thick 6mm cushioning protects joints. Made from eco-friendly TPE material, free from latex and harmful chemicals. Lightweight and portable with carrying strap. Easy to clean. Ideal for yoga, pilates, stretching, meditation, and floor exercises. Perfect for beginners learning proper form and experienced yogis.',
                'price' => 49.99,
                'stock' => 85,
                'category' => 'Sports'
            ],
            [
                'name' => 'Resistance Bands Set',
                'description' => 'Complete home gym resistance band set for full-body workouts. Includes 5 bands with different resistance levels from light to extra heavy. Build strength, tone muscles, improve flexibility. Perfect for rehabilitation, physical therapy, CrossFit, pilates, or general fitness. Compact and portable for travel or outdoor workouts. Includes door anchor, handles, and ankle straps. Suitable for all fitness levels.',
                'price' => 29.99,
                'stock' => 120,
                'category' => 'Sports'
            ],
            [
                'name' => 'Running Shoes with Cushion Technology',
                'description' => 'Lightweight running shoes engineered for comfort and performance. Advanced cushioning absorbs impact and reduces joint stress. Breathable mesh upper keeps feet cool and dry. Responsive foam returns energy with every stride. Durable rubber outsole provides traction on any surface. Perfect for marathon training, jogging, walking, gym workouts. Helps prevent shin splints and plantar fasciitis.',
                'price' => 119.99,
                'stock' => 65,
                'category' => 'Sports'
            ],
            [
                'name' => 'Camping Tent for 4 People',
                'description' => 'Spacious family camping tent with easy setup in under 5 minutes. Waterproof rainfly and sealed seams keep you dry in any weather. Large mesh windows provide ventilation while keeping bugs out. Room divider creates privacy. Storage pockets organize gear. Durable fiberglass poles and stakes included. Perfect for family camping trips, music festivals, backyard sleepovers, and outdoor adventures.',
                'price' => 189.99,
                'stock' => 30,
                'category' => 'Sports'
            ],
            [
                'name' => 'Insulated Water Bottle 32oz',
                'description' => 'Stainless steel vacuum-insulated water bottle keeps drinks cold for 24 hours or hot for 12 hours. BPA-free and leak-proof design. Wide mouth fits ice cubes. Powder-coated exterior provides grip. Fits in car cup holders. Perfect for gym workouts, hiking, cycling, office, school, or travel. Stay hydrated and reduce plastic waste. Available in multiple colors.',
                'price' => 34.99,
                'stock' => 140,
                'category' => 'Sports'
            ],

            // Health & Beauty
            [
                'name' => 'Electric Toothbrush with UV Sanitizer',
                'description' => 'Advanced sonic electric toothbrush removes 10x more plaque than manual brushing. 5 cleaning modes including whitening and gum care. 2-minute timer with 30-second pacer ensures proper brushing. Comes with UV sanitizing case that kills 99.9% of germs. Rechargeable battery lasts 4 weeks. Perfect for achieving dental hygiene excellence and maintaining bright, healthy smile.',
                'price' => 79.99,
                'stock' => 75,
                'category' => 'Health'
            ],
            [
                'name' => 'Aromatherapy Essential Oil Diffuser',
                'description' => 'Ultrasonic essential oil diffuser creates soothing mist for relaxation and wellness. 300ml capacity runs up to 10 hours. 7 LED color options create calming ambiance. Auto shut-off for safety. Whisper-quiet operation ideal for bedroom or office. Use with lavender for sleep, peppermint for focus, eucalyptus for congestion relief. Perfect for stress relief, meditation, yoga, or spa-like atmosphere at home.',
                'price' => 39.99,
                'stock' => 95,
                'category' => 'Health'
            ],
            [
                'name' => 'Digital Body Fat Scale',
                'description' => 'Smart bathroom scale measures not just weight but body fat, muscle mass, BMI, bone density, and water percentage. Syncs with smartphone app to track progress over time. Supports unlimited users with automatic recognition. Tempered glass platform with large LED display. Perfect for fitness enthusiasts, weight loss journeys, bodybuilders, and anyone committed to comprehensive health monitoring.',
                'price' => 49.99,
                'stock' => 80,
                'category' => 'Health'
            ],
            [
                'name' => 'Massage Gun for Deep Tissue',
                'description' => 'Professional percussion massage gun relieves muscle tension and soreness. 6 speeds and 6 interchangeable heads target different muscle groups. Quiet motor won\'t disturb others. Rechargeable battery lasts 6 hours. Comes with carrying case. Perfect for athletes, gym-goers, office workers with back pain, physical therapy, post-workout recovery, and improving circulation.',
                'price' => 129.99,
                'stock' => 50,
                'category' => 'Health'
            ],

            // Books & Education
            [
                'name' => 'Clean Code: A Handbook of Software Craftsmanship',
                'description' => 'Essential programming book teaching principles of writing readable, maintainable code. Learn best practices for naming, functions, comments, formatting, and error handling. Real-world examples and case studies. Perfect for software developers, computer science students, and anyone wanting to elevate their coding skills from amateur to professional level. Timeless wisdom applicable to any programming language.',
                'price' => 44.99,
                'stock' => 60,
                'category' => 'Books'
            ],
            [
                'name' => 'Atomic Habits: Build Better Routines',
                'description' => 'Life-changing book on habit formation and breaking bad habits. Learn proven strategies to make good habits inevitable and bad habits impossible. Discover how tiny changes compound into remarkable results. Backed by scientific research and real stories. Perfect for anyone wanting to improve productivity, health, relationships, or achieve personal goals. Practical framework for lasting change.',
                'price' => 26.99,
                'stock' => 110,
                'category' => 'Books'
            ],
            [
                'name' => 'The Psychology of Money',
                'description' => 'Insightful book about relationship with money and wealth-building. Learn why financial success is more about behavior than intelligence. Timeless lessons on greed, happiness, and making better decisions. Real stories illustrating how people think about money differently. Perfect for investors, savers, entrepreneurs, and anyone wanting financial freedom and peace of mind about money.',
                'price' => 24.99,
                'stock' => 90,
                'category' => 'Books'
            ],

            // Clothing & Fashion
            [
                'name' => 'Merino Wool Base Layer Set',
                'description' => 'Ultra-soft merino wool base layer shirt and pants regulate body temperature in any weather. Naturally moisture-wicking and odor-resistant. Perfect for hiking, skiing, camping, or everyday cold weather wear. Flatlock seams prevent chafing. Machine washable. Ideal for outdoor enthusiasts, winter sports athletes, and anyone who values comfort and performance in active lifestyle.',
                'price' => 89.99,
                'stock' => 45,
                'category' => 'Clothing'
            ],
            [
                'name' => 'Leather Crossbody Bag',
                'description' => 'Genuine leather crossbody bag combines style and functionality. Multiple compartments keep phone, wallet, keys organized. Adjustable strap for comfortable wear. Timeless design works with casual or professional outfits. RFID-blocking pocket protects credit cards from theft. Perfect for daily errands, travel, festivals, or handsfree shopping. Makes thoughtful gift for anyone who values quality craftsmanship.',
                'price' => 79.99,
                'stock' => 55,
                'category' => 'Clothing'
            ],
            [
                'name' => 'Polarized Sunglasses UV Protection',
                'description' => 'Stylish polarized sunglasses block 100% of harmful UVA and UVB rays. Reduce glare from water, snow, and roads for safer driving and outdoor activities. Durable frames and scratch-resistant lenses. Comfortable lightweight design for all-day wear. Includes hard case and cleaning cloth. Perfect for driving, fishing, beach, skiing, or any sunny outdoor adventure.',
                'price' => 59.99,
                'stock' => 70,
                'category' => 'Clothing'
            ],

            // Office & Productivity
            [
                'name' => 'Standing Desk Converter',
                'description' => 'Ergonomic standing desk converter transforms any desk into sit-stand workstation. Height-adjustable with smooth pneumatic lift. Spacious surface fits monitors, keyboard, and mouse. Improves posture, reduces back pain, increases energy and focus. Easy assembly required. Perfect for office workers, remote workers, and anyone wanting health benefits of standing while working without buying new desk.',
                'price' => 249.99,
                'stock' => 25,
                'category' => 'Office'
            ],
            [
                'name' => 'Noise-Cancelling Conference Speaker',
                'description' => 'Professional USB speakerphone for crystal-clear conference calls. 360-degree microphone pickup captures everyone in room. AI-powered noise cancellation eliminates background sounds. Compatible with Zoom, Teams, Skype, and all video conferencing platforms. Plug-and-play setup. Perfect for home offices, meeting rooms, remote teams, and hybrid workers needing professional audio quality.',
                'price' => 149.99,
                'stock' => 40,
                'category' => 'Office'
            ],
            [
                'name' => 'Ergonomic Office Chair',
                'description' => 'Premium ergonomic office chair with lumbar support relieves back pain during long work sessions. Adjustable seat height, armrests, and recline tension customize fit. Breathable mesh back keeps you cool. Smooth-rolling casters protect floors. Supports up to 300 lbs. Perfect for home office, corporate workspace, gaming setup, or anyone spending hours seated wanting comfort and proper posture support.',
                'price' => 299.99,
                'stock' => 20,
                'category' => 'Office'
            ],
            [
                'name' => 'Wireless Keyboard and Mouse Combo',
                'description' => 'Sleek wireless keyboard and mouse combo perfect for minimalist workspaces. Quiet keys and clicks won\'t disturb others in shared spaces. Long battery life up to 2 years. Compact design saves desk space. Reliable 2.4GHz connection with single USB receiver. Works with Windows, Mac, and Chrome OS. Ideal for office workers, students, and anyone valuing clean aesthetic and functionality.',
                'price' => 49.99,
                'stock' => 100,
                'category' => 'Office'
            ],
        ];

        foreach ($products as $index => $productData) {
            $product = new Product(
                $productData['name'],
                $productData['description'],
                Money::fromDecimal($productData['price']),
                $productData['stock'],
                $productData['category']
            );

            $manager->persist($product);

            // Create some low-stock scenarios (every 7th product)
            if (0 === $index % 7) {
                $product->setStock(3); // Low stock
            }

            $this->addReference("product-{$index}", $product);
        }

        $manager->flush();
    }
}
