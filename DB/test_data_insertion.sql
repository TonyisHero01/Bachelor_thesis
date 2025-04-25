TRUNCATE TABLE order_items, orders, Product, customer, Currency,
    category, ProductColor RESTART IDENTITY CASCADE;

INSERT INTO category (id, name, parent_id) VALUES
(1, 'Clothing', NULL),
(2, 'Electronics', NULL),
(3, 'Home & Kitchen', NULL),
(4, 'Books', NULL),
(5, 'Fashion & Apparel', NULL);

INSERT INTO ProductColor (name, hex) VALUES
('Red', '#FF0000'),
('Green', '#00FF00'),
('Blue', '#0000FF'),
('Black', '#000000'),
('White', '#FFFFFF'),
('Yellow', '#FFFF00'),
('Purple', '#800080'),
('Orange', '#FFA500'),
('Gray', '#808080'),
('Pink', '#FFC0CB');

INSERT INTO Currency (name, value, is_default) VALUES
('USD', 1.00, true),
('EUR', 0.93, false),
('GBP', 0.80, false),
('CZK', 23.50, false),
('JPY', 151.30, false),
('CNY', 7.23, false),
('AUD', 1.52, false),
('CAD', 1.36, false),
('CHF', 0.91, false),
('SEK', 10.43, false);

INSERT INTO Product (
    id, name, category, description, number_in_stock, image_urls,
    width, height, length, weight, material, color, price, hidden,
    discount, sku, attributes, version, currency_id, size,
    add_time, created_at, updated_at
) VALUES
(1, 'Classic White T-Shirt', 'Fashion & Apparel', 'Soft cotton t-shirt, breathable and comfortable.', 120, '["/img/white_tshirt.jpg"]',
 NULL, NULL, NULL, 0.20, 'Cotton', 'White', 499.00, false, 50, 'TSHIRT-WHITE-CLASSIC', '{}', 1, 1, 'M',
 NOW(), NOW(), NOW()),

(2, 'Blue Denim Jeans', 'Fashion & Apparel', 'Slim-fit jeans made with high-quality denim.', 80, '["/img/blue_jeans.jpg"]',
 NULL, NULL, NULL, 0.75, 'Denim', 'Blue', 1299.00, false, 100, 'JEANS-BLUE-SLIM', '{}', 1, 1, 'L',
 NOW(), NOW(), NOW()),

(3, 'Leather Jacket', 'Fashion & Apparel', 'Stylish black leather jacket with zip closure.', 30, '["/img/leather_jacket.jpg"]',
 NULL, NULL, NULL, 1.25, 'Leather', 'Black', 3499.00, false, 150, 'JACKET-LEATHER-BLACK', '{}', 1, 1, 'XL',
 NOW(), NOW(), NOW()),

(4, 'Summer Floral Dress', 'Fashion & Apparel', 'Lightweight summer dress with floral print.', 50, '["/img/floral_dress.jpg"]',
 NULL, NULL, NULL, 0.35, 'Polyester', 'Pink', 999.00, false, 80, 'DRESS-FLORAL-SUMMER', '{}', 1, 1, 'S',
 NOW(), NOW(), NOW()),

(5, 'Wool Scarf', 'Fashion & Apparel', 'Cozy wool scarf for chilly weather.', 150, '["/img/wool_scarf.jpg"]',
 NULL, NULL, NULL, 0.15, 'Wool', 'Grey', 399.00, false, 30, 'SCARF-WOOL-GREY', '{}', 1, 1, NULL,
 NOW(), NOW(), NOW()),

(6, 'Canvas Sneakers', 'Fashion & Apparel', 'Casual sneakers perfect for everyday wear.', 60, '["/img/canvas_sneakers.jpg"]',
 NULL, NULL, NULL, 0.85, 'Canvas', 'White', 799.00, false, 70, 'SHOES-SNEAKERS-CANVAS', '{}', 1, 1, '42',
 NOW(), NOW(), NOW()),

(7, 'Black Leggings', 'Fashion & Apparel', 'High-waisted stretch leggings for all-day comfort.', 100, '["/img/black_leggings.jpg"]',
 NULL, NULL, NULL, 0.30, 'Spandex', 'Black', 599.00, false, 60, 'LEGGINGS-BLACK', '{}', 1, 1, 'M',
 NOW(), NOW(), NOW());

UPDATE shop_info
SET
    eshop_name = 'Moda Vogue',
    address = '456 Fashion Avenue, Prague, Czech Republic',
    telephone = '+420 987 654 321',
    email = 'contact@modavogue.cz',
    about_us = 'Welcome to Moda Vogue – your ultimate destination for modern fashion. We bring you stylish, comfortable, and timeless clothing for every occasion.',
    how_to_order = 'Browse our latest collections, select your favorites, add them to the cart, and proceed to secure checkout.',
    business_conditions = 'Please review our terms and conditions before placing an order. Available on our website.',
    privacy_policy = 'Your data is safe with us. We comply fully with GDPR regulations to ensure your privacy.',
    shipping_info = 'We deliver across the Czech Republic. Free delivery on orders above 2000 CZK.',
    payment = 'Payments accepted via card, PayPal, or bank transfer.',
    refund = 'Returns accepted within 14 days for unworn items with tags. Contact support to initiate a return.',
    color_code = '#2E86C1',
    logo = '/assets/images/logo_fashion.png',
    carousel_pictures = '["/assets/images/fashion_banner1.jpg", "/assets/images/fashion_banner2.jpg", "/assets/images/fashion_banner3.jpg"]',
    company_name = 'Moda Vogue s.r.o.',
    cin = '87654321',
    hide_prices = false
WHERE id = 1;
