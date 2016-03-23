SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `login` varchar(64) NOT NULL,
  `password` varchar(32) NOT NULL,
  `email` varchar(127) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `ban_expiration` datetime DEFAULT NULL,
  `is_activated` tinyint(1) NOT NULL DEFAULT '0',
  `comment` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `user` (`id`, `login`, `password`, `email`, `name`, `is_admin`, `ban_expiration`, `is_activated`, `comment`) VALUES
(1, 'user', '5f4dcc3b5aa765d61d8327deb882cf99', 'user@example.org', 'User with Password', 0, '2016-03-31 00:00:00', 1, NULL);
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`),
  ADD UNIQUE KEY `email` (`email`);
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;