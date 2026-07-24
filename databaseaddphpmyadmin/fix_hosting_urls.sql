-- Sửa URL localhost / http còn sót sau khi lên hosting qddesign.shop
-- Chạy trong phpMyAdmin → database qddesign_webshop

UPDATE `slider`
SET `link` = REPLACE(`link`, 'http://localhost/webshop/', '')
WHERE `link` LIKE 'http://localhost/webshop/%';

UPDATE `slider`
SET `link` = REPLACE(`link`, 'https://localhost/webshop/', '')
WHERE `link` LIKE 'https://localhost/webshop/%';

UPDATE `pages`
SET `content` = REPLACE(`content`, 'http://localhost/webshop/', 'https://qddesign.shop/')
WHERE `content` LIKE '%http://localhost/webshop/%';
