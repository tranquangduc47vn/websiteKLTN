-- Export local DB for hosting (product images match upload/product)
-- Generated: 2026-07-24 11:55:16

SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

TRUNCATE TABLE `catalog`;
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('1','Thời trang','','0','1','2017-04-22 05:35:21');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('2','Bán chạy','','0','2','2017-04-22 05:35:48');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('3','Khuyến mại','','0','3','2017-04-22 05:35:59');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('4','Tin tức','','0','4','2017-04-22 05:36:13');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('5','Giỏ hàng','','0','6','2017-04-22 05:36:49');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('6','Liên hệ','','0','5','2017-04-22 05:37:02');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('7','Thời trang nam','','1','1','2017-04-22 05:37:23');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('8','Thời trang nữ','','1','2','2017-04-22 05:37:36');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('9','Quần áo gia đình','','1','3','2017-04-22 05:37:50');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('10','Áo phông nam','','7','1','2017-04-22 09:08:19');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('11','Áo sơ mi nam','','7','2','2017-04-22 09:08:36');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('12','Quần Jeans','','7','3','2017-04-22 09:09:01');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('13','Quần Kali','','7','4','2017-04-22 09:09:14');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('14','Quần Short','','7','5','2017-04-22 09:09:31');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('15','Đầm','','8','1','2017-04-22 09:09:46');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('16','Áo','','8','2','2017-04-22 09:10:10');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('17','Chân Váy','','8','3','2017-04-22 09:23:39');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('18','Quần','','8','4','2017-04-22 09:23:57');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('19','Áo gia đình hè','','9','1','2017-04-22 09:25:55');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('20','Áo váy gia đình','','9','2','2017-04-22 09:26:21');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('21','Mẹ và bé','','9','4','2017-04-22 09:26:34');
INSERT INTO `catalog` (`id`,`name`,`description`,`parent_id`,`sort_order`,`created`) VALUES ('25','Áo Khoác','','8','5','2026-07-21 17:06:16');

TRUNCATE TABLE `product`;
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('1','16','Áo kiểu cổ polo cộc tay','','Đen,Xanh dương,Hồng','S,M,L','355000.00','0','percent','0','Ao-kieu-co-polo-coc-tay.jpg','[\"Ao-kieu-co-polo-coc-tay-Den-L-1.jpg\",\"Ao-kieu-co-polo-coc-tay-Xanh-duong-nhat-L-1.jpg\"]','1','0','37','4','1','1784824058','SP00001','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('2','16','Áo kiểu cổ tròn dáng ôm suông khăn buộc','','Xanh dương,Hồng,Nâu','S,M,L,XL','295000.00','0','percent','0','Ao-kieu-co-tron-dang-om-suong-khan-buoc.jpg','[\"Ao-kieu-co-tron-dang-om-suong-khan-buoc-Nau-S-1.jpg\",\"Ao-kieu-co-tron-dang-om-suong-khan-buoc-Xanh-than-M.jpg\"]','1','0','0','4','1','1784824258','SP00002','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('3','17','Chân váy đổ 2 tầng đan dây','','Trắng,Hồng','S,M,L','595000.00','0','percent','0','20250217_73pxosopqI.jpeg','[\"20250217_kPFtYW4xao.jpeg\",\"20250220_50SZGx0qtr.jpeg\",\"20250228_SRQJXikg8C.jpeg\"]','0','0','9','4','1','1784824457','SP00003','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('4','17','Chân váy dáng dài đổ','','Hồng','S,M,L','655000.00','0','percent','0','Chan-vay-dang-dai-do.jpg','[\"Chan-vay-dang-dai-do-Be-L-1.jpg\",\"Chan-vay-dang-dai-do-Be-L-2.jpg\"]','1','1','5','4','1','1784824536','SP00004','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('5','15','Đầm Xuân Hè chân đổ tay chờm','','Trắng,Hồng','S,M,L','875000.00','0','percent','0','Dam-Xuan-He-chan-do-tay-chom.jpg','[\"Dam-Xuan-He-chan-do-tay-chom-Hong-Phot-S.jpg\",\"Dam-Xuan-He-chan-do-tay-chom-Kem-L.jpg\"]','0','0','0','4','1','1784825001','SP00005','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('6','15','Đầm Party ngang vai nhún hông','','Xanh dương,Hồng','S,M,L','985000.00','0','percent','0','Dam-Party-ngang-vai-nhun-hong-Xanh-Duong-Nhat-S.jpg','[\"Dam-Party-ngang-vai-nhun-hong.jpg\",\"Dam-Party-ngang-vai-nhun-hong-Kem-S.jpg\"]','1','0','0','4','1','1784825296','SP00006','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('7','18','Quần Resort 2 ly bung','','Trắng,Kem','S,M,L','655000.00','0','percent','0','Quan-Resort-2-ly-bung.jpg','[\"Quan-Resort-2-ly-bung-Be-L.jpg\",\"Quan-Resort-2-ly-bung-Be-S.jpg\",\"Quan-Resort-2-ly-bung-Ghi-S.jpg\"]','1','0','0','4','1','1784825396','SP00007','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('8','18','Quần jean ống loe 2 túi','','Đen,Trắng','S,M,L','585000.00','0','percent','0','Quan-jean-ong-loe-2-tui-Trang-M.jpg','[\"Quan-jean-ong-loe-2-tui.jpg\",\"Quan-jean-ong-loe-2-tui-Den-M.jpg\"]','2','0','28','5','1','1784826080','SP00008','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('9','25','Áo dạ 2 hàng cúc, cổ ve mèo','','Kem','S,M','2285000.00','0','percent','0','Ao-da-2-hang-cuc-co-ve-meo-Kem-S.jpg','[\"Ao-da-2-hang-cuc-co-ve-meo.jpg\",\"Ao-da-2-hang-cuc-co-ve-meo-Kem-M.jpg\"]','1','0','0','4','1','1784827314','SP00009','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('10','25','Áo dạ suông cổ tròn bèo 2 nắp túi','','Kem','S,M','1385000.00','0','percent','0','Ao-da-suong-co-tron-beo-2-nap-tui-1.jpg','[]','1','0','0','2','1','1784827364','SP00010','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('11','25','Áo dáng ngắn cổ đức rời tay dài','','Đỏ,Nâu,Kem','S,M,L,XL','1125000.00','0','percent','0','Ao-dang-ngan-co-duc-roi-tay-dai.jpg','[\"Ao-dang-ngan-co-duc-roi-tay-dai-Do-M.jpg\",\"Ao-dang-ngan-co-duc-roi-tay-dai-Nau-S-3.jpg\"]','2','0','0','4','1','1784827423','SP00011','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('12','11','Áo Sơ Mi Nam Tay Dài Warp Knit Form Slim','','Xanh dương,Xám','S,M,L,XL','449000.00','0','percent','0','smid0351_qtid0077__2__734afddaf773407ea7ee571b7be2b3bd_1024x1024.webp','[\"ao-so-mi-nam-tay-dai-warp-knit-form-slim52_a6228a1e044a46e9856619caeb2c3e06_compact.webp\",\"ao-so-mi-nam-tay-dai-warp-knit-form-slim56_eb2ae453aed4419e95c620bc68a348c9_1024x1024.webp\"]','0','0','0','4','1','1784889673','SP00012','1');
INSERT INTO `product` (`id`,`catalog_id`,`name`,`content`,`color`,`size`,`price`,`discount`,`discount_type`,`discount_percent`,`image_link`,`image_list`,`view`,`buyed`,`quantity`,`rate_total`,`rate_count`,`created`,`code`,`status`) VALUES ('13','11','Áo Sơ Mi Nam Tay Ngắn Linen Cuban Sandrift Form Relaxed','','Xám,Nâu','S,M,L,XL','379000.00','0','percent','0','20260624_04_5ac45ec4247040c185885b138b371df5_1024x1024.webp','[\"ao-so-mi-nam-tay-ngan-linen-cuban-sandrift-form-relaxed-1_3dd7ec440d8a4534b0bb5b808b082713_compact.webp\",\"ao-so-mi-nam-tay-ngan-linen-cuban-sandrift-form-relaxed-3_60832ef9ba8a441ea3457024ec3ff908_compact.webp\"]','0','0','0','4','1','1784889866','SP00013','1');

-- Skip missing table: product_variant

TRUNCATE TABLE `product_inventory`;

-- Skip missing table: product_image

TRUNCATE TABLE `slider`;
INSERT INTO `slider` (`id`,`name`,`image_link`,`link`,`sort_order`,`created`) VALUES ('4','1','slide.jpg','khoanh-khac-thanh-thoi-p1','1','2017-04-25 15:36:41');
INSERT INTO `slider` (`id`,`name`,`image_link`,`link`,`sort_order`,`created`) VALUES ('5','2','slide1.jpg','phong-cach-phoi-do-p2','2','2017-04-25 15:37:00');
INSERT INTO `slider` (`id`,`name`,`image_link`,`link`,`sort_order`,`created`) VALUES ('14','3','slide4.jpg','tinh-yeu-p3','3','2026-06-10 04:23:22');

SET FOREIGN_KEY_CHECKS=1;
