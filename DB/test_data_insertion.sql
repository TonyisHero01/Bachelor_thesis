-- 清空所有相关表并重置自增 ID
TRUNCATE TABLE order_items, orders, Product, customer, Currency,
    category, ProductColor RESTART IDENTITY CASCADE;

-- 只保留 4 个 category
INSERT INTO category (id, name, parent_id) VALUES
(1, 'Clothing', NULL),
(2, 'Electronics', NULL),
(3, 'Home & Kitchen', NULL),
(4, 'Books', NULL);

-- 插入产品颜色
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

-- 插入货币
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

-- 插入商品数据
INSERT INTO Product (
    id, name, category, description, number_in_stock, image_urls,
    width, height, length, weight, material, color, price, hidden,
    discount, sku, attributes, version, currency_id, size,
    add_time, created_at, updated_at
) VALUES
(101, 'Salmon Nigiri', 'Home & Kitchen', 'Fresh salmon over seasoned rice.', 50, '["/img/salmon_nigiri1.jpg"]',
 NULL, NULL, NULL, 0.10, 'Fish', 'Orange', 45.25, false, 100, 'SUSHI-SALMON-NIGIRI', '{}', 1, 1, 'M',
 NOW(), NOW(), NOW()),

(102, 'Miso Soup', 'Home & Kitchen', 'Traditional Japanese soup with tofu and seaweed.', 80, '["/img/miso_soup.jpg"]',
 NULL, NULL, NULL, 0.30, 'Ceramic Bowl', 'Brown', 30.00, false, 100, 'SOUP-MISO', '{}', 1, 1, NULL,
 NOW(), NOW(), NOW()),

(103, 'Avocado Roll', 'Home & Kitchen', 'Vegetarian sushi roll with avocado.', 60, '["/img/avocado_roll.jpg"]',
 NULL, NULL, NULL, 0.15, 'Rice & Avocado', 'Green', 29.99, false, 100, 'ROLL-AVOCADO', '{}', 1, 1, 'S',
 NOW(), NOW(), NOW()),

(104, 'Tuna Sashimi', 'Home & Kitchen', 'Slices of fresh raw tuna.', 40, '["/img/tuna_sashimi.jpg"]',
 NULL, NULL, NULL, 0.12, 'Fish', 'Red', 45.00, false, 100, 'SASHIMI-TUNA', '{}', 1, 1, 'M',
 NOW(), NOW(), NOW()),

(105, 'Dragon Roll', 'Home & Kitchen', 'Special roll with eel, avocado, and cucumber.', 30, '["/img/dragon_roll.jpg"]',
 NULL, NULL, NULL, 0.25, 'Various', 'Green', 75.00, false, 100, 'ROLL-DRAGON', '{}', 1, 1, 'L',
 NOW(), NOW(), NOW()),

(106, 'Green Tea', 'Beauty & Personal Care', 'Traditional Japanese matcha green tea.', 100, '["/img/green_tea.jpg"]',
 NULL, NULL, NULL, 0.05, 'Powder', 'Green', 20.00, false, 100, 'DRINK-GREENTEA', '{}', 1, 1, NULL,
 NOW(), NOW(), NOW()),

(107, 'Tempura Shrimp', 'Home & Kitchen', 'Crispy fried shrimp in light batter.', 35, '["/img/tempura_shrimp.jpg"]',
 NULL, NULL, NULL, 0.20, 'Seafood', 'Golden', 44.75, false, 100, 'TEMPURA-SHRIMP', '{}', 1, 1, 'M',
 NOW(), NOW(), NOW());

-- 更新商店信息
UPDATE shop_info
SET
    eshop_name = 'Koko Sushi',
    address = '123 Sushi Street, Prague, Czech Republic',
    telephone = '+420 123 456 789',
    email = 'info@kokosushi.cz',
    about_us = 'Welcome to Koko Sushi, where tradition meets taste. We serve authentic Japanese cuisine with the freshest ingredients.',
    how_to_order = 'Choose your favorite items, add them to your cart, and proceed to checkout. Orders can be picked up or delivered.',
    business_conditions = 'All sales are subject to our business terms and conditions available on our website.',
    privacy_policy = 'We respect your privacy and handle your data in compliance with GDPR.',
    shipping_info = 'Delivery is available within Prague. Free delivery for orders over 1000 CZK.',
    payment = 'We accept cash, card, and online payments.',
    refund = 'Refunds are possible within 24 hours for unconsumed items. Contact our support.',
    color_code = '#FF6B6B',
    logo = '/assets/images/logo.png',
    carousel_pictures = '["/assets/images/banner1.jpg", "/assets/images/banner2.jpg", "/assets/images/banner3.jpg"]',
    company_name = 'Koko Sushi s.r.o.',
    cin = '12345678',
    hide_prices = false
WHERE id = 1;
