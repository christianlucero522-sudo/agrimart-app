/* =========================================================
   AGRIMART — STATIC DEMO DATA
   Front-end only build: this file stands in for a database.
   Swap this out for real API/PHP calls later.
   ========================================================= */

const CATEGORIES = [
  { category_id: 1, category_name: "Vegetable Seeds", category_type: "seed" },
  { category_id: 2, category_name: "Grain Seeds", category_type: "seed" },
  { category_id: 3, category_name: "Fruit Seeds", category_type: "seed" },
  { category_id: 4, category_name: "Tractors", category_type: "equipment" },
  { category_id: 5, category_name: "Irrigation Tools", category_type: "equipment" },
  { category_id: 6, category_name: "Harvesting Tools", category_type: "equipment" },
  { category_id: 7, category_name: "Hand Tools", category_type: "equipment" }
];

const PRODUCTS = [
  {
    product_id: 1,
    category_id: 1,
    product_name: "Native Tomato Seeds",
    description: "Open-pollinated tomato seeds, high yield, disease resistant. Great for backyard plots and small commercial rows alike.",
    price: 85.00,
    quantity: 200,
    unit: "pack",
    image_url: "assets/images/product-tomato.svg",
    seller_name: "Mang Delfin Cruz",
    farm_name: "Cruz Family Farm",
    farm_location: "Villasis, Pangasinan",
    status: "active"
  },
  {
    product_id: 2,
    category_id: 2,
    product_name: "Certified Rice Seeds (NSIC Rc222)",
    description: "High-yield inbred rice seed, 110-day maturity. Certified by the National Seed Industry Council.",
    price: 60.00,
    quantity: 500,
    unit: "kg",
    image_url: "assets/images/product-rice.svg",
    seller_name: "Mang Delfin Cruz",
    farm_name: "Cruz Family Farm",
    farm_location: "Villasis, Pangasinan",
    status: "active"
  },
  {
    product_id: 3,
    category_id: 3,
    product_name: "Calamansi Seedlings",
    description: "Grafted calamansi seedlings, fruits within 2 years. Sold as bare-root seedlings ready for transplant.",
    price: 120.00,
    quantity: 80,
    unit: "pc",
    image_url: "assets/images/product-calamansi.svg",
    seller_name: "Mang Delfin Cruz",
    farm_name: "Cruz Family Farm",
    farm_location: "Villasis, Pangasinan",
    status: "active"
  }
];

const EQUIPMENT = [
  {
    equipment_id: 1,
    category_id: 4,
    equipment_name: "Hand Tractor",
    description: "Diesel-powered hand tractor for plowing and tilling. Well-maintained, serviced every 3 months.",
    brand: "Kubota",
    model: "KT-140",
    listing_type: "both", // "rent" | "sale" | "both"
    rate_type: "daily",
    rate_price: 1500.00,
    sale_price: 85000.00,
    availability: "available",
    image_url: "assets/images/equip-tractor.svg",
    owner_name: "Tomas Equipment Rentals",
    status: "active"
  },
  {
    equipment_id: 2,
    category_id: 5,
    equipment_name: "Water Pump Irrigation Set",
    description: "Portable irrigation pump with 100m hose. Ideal for rice paddies and vegetable plots.",
    brand: "Honda",
    model: "WB20XT",
    listing_type: "rent",
    rate_type: "daily",
    rate_price: 650.00,
    sale_price: null,
    availability: "available",
    image_url: "assets/images/equip-pump.svg",
    owner_name: "Tomas Equipment Rentals",
    status: "active"
  },
  {
    equipment_id: 3,
    category_id: 6,
    equipment_name: "Rice Combine Harvester",
    description: "Mini combine harvester for small to mid-sized fields. Includes operator training on purchase.",
    brand: "Yanmar",
    model: "AW70",
    listing_type: "sale",
    rate_type: "hourly",
    rate_price: 900.00,
    sale_price: 620000.00,
    availability: "available",
    image_url: "assets/images/equip-harvester.svg",
    owner_name: "Tomas Equipment Rentals",
    status: "active"
  },
  {
    equipment_id: 4,
    category_id: 7,
    equipment_name: "Round-Point Shovel",
    description: "Heavy-gauge steel shovel with a hardwood handle. Good for digging, edging, and turning soil.",
    brand: "Ace Hardware",
    model: "RS-01",
    listing_type: "sale",
    rate_type: "daily",
    rate_price: 40.00,
    sale_price: 320.00,
    availability: "available",
    image_url: "assets/images/tool-shovel.svg",
    owner_name: "Tomas Equipment Rentals",
    status: "active"
  },
  {
    equipment_id: 5,
    category_id: 7,
    equipment_name: "Garden Hoe",
    description: "All-purpose hoe for weeding, cultivating, and shaping furrows. Comfortable rubber grip.",
    brand: "Ace Hardware",
    model: "GH-02",
    listing_type: "sale",
    rate_type: "daily",
    rate_price: 30.00,
    sale_price: 250.00,
    availability: "available",
    image_url: "assets/images/tool-hoe.svg",
    owner_name: "Tomas Equipment Rentals",
    status: "active"
  },
  {
    equipment_id: 6,
    category_id: 7,
    equipment_name: "Bolo Knife",
    description: "Traditional Filipino bolo for clearing brush, harvesting, and general farm chores. Carbon steel blade.",
    brand: "Ilocos Forge",
    model: "BK-14",
    listing_type: "sale",
    rate_type: "daily",
    rate_price: 20.00,
    sale_price: 180.00,
    availability: "available",
    image_url: "assets/images/tool-bolo.svg",
    owner_name: "Tomas Equipment Rentals",
    status: "active"
  },
  {
    equipment_id: 7,
    category_id: 7,
    equipment_name: "Steel Garden Rake",
    description: "Wide-head steel rake for leveling soil, clearing debris, and prepping seed beds.",
    brand: "Ace Hardware",
    model: "GR-05",
    listing_type: "sale",
    rate_type: "daily",
    rate_price: 25.00,
    sale_price: 220.00,
    availability: "available",
    image_url: "assets/images/tool-rake.svg",
    owner_name: "Tomas Equipment Rentals",
    status: "active"
  },
  {
    equipment_id: 8,
    category_id: 7,
    equipment_name: "Pruning Shears",
    description: "Bypass pruning shears for trimming branches, vines, and small stems. Non-stick coated blade.",
    brand: "Fiskars",
    model: "PS-8",
    listing_type: "sale",
    rate_type: "daily",
    rate_price: 15.00,
    sale_price: 190.00,
    availability: "available",
    image_url: "assets/images/tool-shears.svg",
    owner_name: "Tomas Equipment Rentals",
    status: "active"
  }
];
