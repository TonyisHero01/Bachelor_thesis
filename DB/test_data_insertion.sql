TRUNCATE TABLE order_items, orders, Product, customer, Currency,
    category, ProductColor RESTART IDENTITY CASCADE;

INSERT INTO category (id, name, parent_id)
VALUES (1, 'Electronics', NULL),
       (2, 'Laptops', 1),
       (3, 'Phones', 1),
       (4, 'Books', 1),
       (5, 'Fashion', 1);

INSERT INTO productcolor (id, name, hex)
VALUES (1, 'Black', '#000000'),
       (2, 'White', '#FFFFFF'),
       (3, 'Blue', '#0000FF');

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

INSERT INTO product (
    id, name, description, number_in_stock, image_urls, add_time, width, height, length, weight,
    material, price, hidden, discount, attributes, version, sku, currency_id, tax_rate,
    category_id, color_id, size_id
)
VALUES
(1, 'Laptop Pro 15', 'High performance laptop.', 20, '["img1.jpg","img2.jpg"]', '2024-05-01',
 35.0, 2.0, 24.0, 1.5, 'Aluminum', 1500.00, false, 90, '{}', 1, 'SKU001', 1, 21, 2, 1, NULL),

(2, 'Smartphone X', 'Latest smartphone.', 50, '["phone.jpg"]', '2024-05-01',
 15.0, 0.8, 7.5, 0.2, 'Glass', 999.99, false, 100, '{}', 1, 'SKU002', 1, 21, 3, 3, NULL);

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

SELECT setval('product_id_seq', (SELECT MAX(id) FROM product) + 1);

SELECT setval('category_id_seq', (SELECT MAX(id) FROM category) + 1);

SELECT setval('currency_id_seq', (SELECT MAX(id) FROM currency) + 1);

SELECT setval('public.productcolor_id_seq', (SELECT MAX(id) FROM productcolor) + 1);

SELECT setval('shop_info_id_seq', (SELECT MAX(id) FROM shop_info) + 1);