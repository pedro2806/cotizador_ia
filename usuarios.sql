-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 28-04-2026 a las 11:46:10
-- Versión del servidor: 8.0.46
-- Versión de PHP: 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `mess_rrhh`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `usuario` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `password` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `noEmpleado` varchar(11) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `correo` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `puesto` int NOT NULL,
  `region` int NOT NULL,
  `departamento` int NOT NULL,
  `fechaIngreso` date NOT NULL,
  `estatus` int NOT NULL,
  `rol` int NOT NULL,
  `jefe` varchar(50) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  `diasdisponibles` varchar(50) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  `tipoContrato` varchar(50) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  `sexo` varchar(2) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  `nss` varchar(25) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  `rfc` varchar(20) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  `curp` varchar(25) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  `tipoSangre` varchar(15) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  `id_usuario` int NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `password`, `noEmpleado`, `nombre`, `correo`, `puesto`, `region`, `departamento`, `fechaIngreso`, `estatus`, `rol`, `jefe`, `diasdisponibles`, `tipoContrato`, `sexo`, `nss`, `rfc`, `curp`, `tipoSangre`, `id_usuario`) VALUES
(199, 'sebastian.gutierrez@mess.com.mx', 'sebas123', '523', 'Sebastian Gutierrez Rodriguez', 'sebastian.gutierrez@mess.com.mx', 61, 4, 32, '2025-02-05', 1, 1, '183', '0', 'PLANTA', 'M', '27150153347', 'GURS0106173A3', 'GURS010617HQTTDDA0', 'A+', 495),
(2, 'silvia@mess.com.mx', 'silvia', '2', 'Avendaño Govea Patricia Silvia', 'silvia@mess.com.mx', 23, 4, 1, '2009-10-01', 1, 3, '19', '0', 'PLANTA', 'F', '1479600448-0', 'AEGP600921KR7', 'AEGP600921MQTVVT01', 'ORH+', 5),
(3, 'botelloirmis@mess.com.mx', 'nietos', '3', 'Botello Pérez Irma ', 'botelloirmis@gmail.com', 65, 4, 1, '2012-06-11', 1, 1, '2', '19', 'PLANTA', 'F', '1489690890-3', 'BOPI690924EY6', 'BOPI690924MQTTRR04', 'ORH+', 99999),
(4, 'marypaz.cruz@mess.com.mx', 'marypaz.cruz', '5', 'Cruz Cruz María de la Paz', 'marypaz.cruz@mess.com.mx', 45, 4, 4, '2013-01-07', 1, 3, '19', '20', 'PLANTA', 'F', '1397781278-6', 'CUCP78012493A', 'CUCP780124MHGRRZ05', 'BRH+', 29),
(5, 'erik@mess.com.mx', 'erik', '7', 'Gallardo Calderón Erik ', 'erik@mess.com.mx', 64, 4, 40, '2013-04-01', 1, 1, '324', '23', 'PLANTA', 'M', '1406898157-1', 'GACE890209CX6', 'GACE890209HQTLLR00', 'ORH+', 74),
(6, 'lucrecia@mess.com.mx', 'llino2308', '10', 'Lino Salinas Lucrecia ', 'lucrecia@mess.com.mx', 46, 4, 7, '2012-01-30', 1, 3, '2', '14', 'PLANTA', 'F', '1288732606-9', 'LISL730911V60', 'LISL730911MQTNLC02', 'ORH+', 76),
(7, 'jorge@mess.com.mx', 'JorgeMess9', '11', 'Mancilla Silva Jorge Luis', 'jorge@mess.com.mx', 51, 8, 5, '2010-02-01', 1, 3, '19', '24', 'PLANTA', 'M', '1408862122-3', 'MASJ860709L52', 'MASJ860709HQTNLR09', 'ORH+', 30),
(8, 'miguel@mess.com.mx', 'miguel', '13', 'Martínez Cruz José Miguel', 'miguel@mess.com.mx', 52, 4, 36, '2012-08-14', 1, 1, '45', '1', 'PLANTA', 'M', '6892741767-2', 'MACM740315TQ5', 'MACM740315HOCRRG01', 'ORH+', 21),
(9, 'lab.fuerza@mess.com.mx', 'Morpheus21', '14', 'Meléndez Acevedo Fabián ', 'lab.fuerza@mess.com.mx', 55, 4, 21, '2012-06-06', 1, 3, '521', '24', 'PLANTA', 'M', '1407881930-8', 'MEAF8810083C2', 'MEAF881008HGTLCB07', 'ARH+', 53),
(10, 'ivan@mess.com.mx', 'megi7608', '15', 'Méndez García Iván Alejandro', 'ivan@mess.com.mx', 23, 4, 30, '2009-10-01', 1, 3, '19', '80', 'PLANTA', 'M', '3700760530-8', 'MEGI7608017X5', 'MEGI760801HGTNRV06', '', 22),
(11, 'gerardo@mess.com.mx', 'germoc', '16', 'Moctezuma Flores Gerardo ', 'gerardo@mess.com.mx', 43, 4, 1, '2011-11-14', 1, 1, '189', '13', 'PLANTA', 'M', '1311881086-3', 'MOFG880707ID2', 'MOFG880707HHGCLR04', 'ARH+', 64),
(12, 'alberto@mess.com.mx', 'alberto', '18', 'Morales García Jorge Alberto', 'alberto@mess.com.mx', 23, 4, 21, '2009-10-01', 1, 1, '19', '80', 'PLANTA', 'M', '1489703763-7', 'MOGJ700609AV7', 'MOGJ700609HDFRRR00', '', 48),
(13, 'oscar@mess.com.mx', 'direccion2026', '19', 'Morales García José Oscar Tomás', 'oscar@mess.com.mx', 24, 4, 10, '2009-10-01', 1, 3, '19', '80', 'PLANTA', 'M', '6285681603-8', 'MOGO6812167P3', 'MOGO681216HDFRRS06', '', 6),
(14, 'omar@mess.com.mx', 'omar2025', '20', 'Morales García Omar Israel', 'omar@mess.com.mx', 23, 4, 36, '2009-10-01', 1, 3, '19', '80', 'PLANTA', 'M', '1404770043-1', 'MOGO770528317', 'MOGO770528HGTRRM06', '', 50),
(15, 'cesar.ramirez@mess.com.mx', 'cesar.ramirez', '25', 'Ramírez García César ', 'cesar.ramirez@mess.com.mx', 38, 4, 2, '2015-01-16', 0, 3, '19', '15', 'PLANTA', 'M', '1410860118-9', 'RAGC860916DK5', 'RAGC860916HQTMRS11', '', 478),
(16, 'esmeralda@mess.com.mx', 'esmeralda', '26', 'Ramírez López Esmeralda Patricia', 'esmeralda@mess.com.mx', 58, 6, 40, '2010-10-05', 1, 1, '15', '24', 'PLANTA', 'F', '8885640235-1', 'RALE640612AW3', 'RALE640612MMNMPS01', 'N/D', 10),
(17, 'dulcer@mess.com.mx', 'Santi1405', '27', 'Reyes Hernández Dulce María', 'dulcer@mess.com.mx', 62, 4, 1, '2012-05-28', 1, 1, '2', '13', 'PLANTA', 'F', '1408841075-9', 'REHD840912JY8', 'REHD840912MQTYRL05', '', 15),
(18, 'adm_mess@mess.com.mx', 'adm_mess', '28', 'Rodríguez Barba Nora Liliana', 'adm_mess@mess.com.mx', 58, 4, 1, '2013-01-07', 1, 1, '2', '14', 'PLANTA', 'F', '1497740198-4', 'ROBN740225TB1', 'ROBN740225MVZDRR06', '', 14),
(19, 'carlos.guzman@mess.com.mx', 'carlos.guzman', '33', 'Guzmán Reyes Carlos Fernando', 'carlos.guzman@mess.com.mx', 47, 4, 19, '2013-04-08', 1, 3, '19', '21', 'PLANTA', 'M', '3705720003-4', 'GURC7202218V9', 'GURC720221HDFZYR19', 'N/D', 23),
(20, 'omaral@mess.com.mx', 'omaral', '37', 'López Corral Omar Alejandro', 'omaral@mess.com.mx', 29, 1, 40, '2013-06-10', 1, 3, '19', '24', 'PLANTA', 'M', '2413860598-5', 'LOCO860819HKA', 'LOCO860819HSRPRM03', 'ORH+', 44),
(21, 'fernando@mess.com.mx', 'fernando', '39', 'Romero Espinosa Fernando ', 'fernando@mess.com.mx', 58, 4, 40, '2013-06-10', 1, 1, '15', '-2', 'PLANTA', 'M', '1311860860-6', 'ROEF860220MR0', 'ROEF860220HHGMSR09', 'ORH+', 70),
(22, 'fernanda@mess.com.mx', 'fernanda', '41', 'Ríos Nieto María Fernanda', 'fernanda@mess.com.mx', 17, 4, 24, '2013-06-17', 1, 1, '19', '12', 'PLANTA', 'F', '1406917353-3', 'RINF9103276P1', 'RINF910327MQTSTR08', 'ARH+', 33),
(23, 'vrico@mess.com.mx', 'DANIELDR', '42', 'Rico Corona María Virginia', 'vrico@mess.com.mx', 48, 4, 25, '2013-07-16', 1, 3, '521', '24', 'PLANTA', 'F', '1409903547-0', 'RICV900813SS8', 'RICV900813MQTCRR06', 'ARH+', 31),
(24, 'sergio@mess.com.mx', 'Ch25xwg@92', '45', 'Cota Luque Sergio Adán', 'sergio@mess.com.mx', 54, 4, 36, '2013-10-07', 1, 3, '20', '3', 'PLANTA', 'M', '2310882067-3', 'COLS880330A87', 'COLS880330HSLTQR02', 'ARH+', 58),
(25, 'ivan.rosales@mess.com.mx', 'ivan.rosales', '46', 'Rosales Pérez Iván ', 'ivan.rosales@mess.com.mx', 38, 8, 5, '2013-10-28', 1, 1, '11', '16', 'PLANTA', 'M', '3213890252-6', 'ROPI890720GK5', 'ROPI890720HCLSRV00', '', 69),
(26, 'david.jimenez@mess.com.mx', 'david.jimenez', '47', 'Jiménez Pérez César David', 'david.jimenez@mess.com.mx', 38, 4, 36, '2013-10-28', 1, 1, '45', '13', 'PLANTA', 'M', '1310850190-2', 'JIPC8512017N7', 'JIPC851201HHGMRS06', 'ORH+', 59),
(27, 'hilda.estrada@mess.com.mx', 'hilda.estrada', '56', 'Estrada Rodríguez María Hilda', 'hilda.estrada@mess.com.mx', 65, 4, 1, '2014-04-23', 1, 1, '2', '6', 'PLANTA', 'F', '1401740105-1', 'EARH7401024M1', 'EARH740102MQTSDL01', '', 99999),
(28, 'lab.par@mess.com.mx', 'lab.par', '61', 'Garduño Cortés José Antonio', 'lab.par@mess.com.mx', 38, 4, 21, '2014-05-29', 1, 1, '14', '21', 'PLANTA', 'M', '3814928653-6', 'GACA920504IE7', 'GACA920504HHGRRN05', 'ORH+', 54),
(29, 'abelardo@mess.com.mx', 'abelardo', '68', 'Luna Álvarez Abelardo ', 'abelardo@mess.com.mx', 38, 8, 5, '2014-09-01', 1, 1, '11', '17', 'PLANTA', 'M', '4303841831-5', 'LUAA8411031C1', 'LUAA841103HNLNLB06', 'ORH+', 73),
(30, 'nayeli.trejo@mess.com.mx', 'nayeli.trejo', '71', 'Trejo Hernández Nayeli Alejandra', 'nayeli.trejo@mess.com.mx', 7, 4, 2, '2014-09-01', 1, 3, '19', '6', 'PLANTA', 'F', '1413900387-6', 'TEHN901211TX1', 'TEHN901211MQTRRY04', 'BRH+', 32),
(31, 'arnoldo@mess.com.mx', 'arnoldo', '81', 'Lara Reyes Arnoldo ', 'arnoldo@mess.com.mx', 51, 10, 5, '2015-02-16', 0, 3, '288', '14', 'PLANTA', 'M', '1014912760-6', 'LARA9105015R6', 'LARA910501HSLRYR02', 'ORH+', 63),
(32, 'cinthia.garcia@mess.com.mx', 'LULIPAMPIN', '85', 'García Meza Cinthia ', 'cinthia.garcia@mess.com.mx', 18, 10, 40, '2015-04-20', 1, 3, '15', '11', 'PLANTA', 'F', '1408907609-6', 'GAMC9003213K6', 'GAMC900321MMCRZN15', 'ARH+', 49),
(33, 'maury@mess.com.mx', 'maury', '90', 'Maury Toledo Augusto ', 'maury@mess.com.mx', 27, 4, 23, '2015-09-16', 1, 1, '19', '17', 'PLANTA', 'M', '0215738819-6', 'MATA731227G30', 'MATA731227HNERLG07', '', 123),
(34, 'marilyn@mess.com.mx', 'marilyn', '95', 'López Pino Marilyn Eneida', 'marilyn@mess.com.mx', 31, 4, 4, '2015-09-16', 1, 1, '15', '0', 'PLANTA', 'F', '0215665911-8', 'LOPM660620NF5', 'LOPM660620MNEPNR06', 'ORH+', 100),
(35, 'rafael@mess.com.mx', 'rafael', '100', 'Arroyo González Rafael ', 'rafael@mess.com.mx', 2, 4, 7, '2015-11-09', 1, 1, '10', '12', 'PLANTA', 'M', '2199809141-3', 'AOGR800613LB4', 'AOGR800613HGTRNF09', 'ARH+', 116),
(36, 'manuel.mendoza@mess.com.mx', 'manuel.mendoza', '101', 'Mendoza Pantoja José Manuel', 'manuel.mendoza@mess.com.mx', 43, 4, 19, '2015-12-16', 1, 1, '161', '4', 'PLANTA', 'M', '148667115-5', 'MEMP670525', 'MEMP670525HQTNNN04', 'ARH+', 89),
(37, 'ricardo.basilio@mess.com.mx', 'ricardo.basilio', '104', 'Basilio Torres Ricardo ', 'ricardo.basilio@mess.com.mx', 43, 4, 21, '2016-01-01', 1, 1, '14', '19', 'PLANTA', 'M', '5714956064-1', 'BATR9508022X9', 'BATR950802HQTSRC07', 'ORH+', 91),
(38, 'brenda@mess.com.mx', 'JAMM88', '107', 'Morales García  Brenda Elizabeth', 'brenda@mess.com.mx', 47, 4, 17, '2016-01-11', 1, 1, '123', '17', 'PLANTA', 'F', '1410880507-9', 'MOGB880311PK2', 'MOGB880311MGTRRR06', 'ORH+', 121),
(39, 'fernando.sanjuan@mess.com.mx', 'fernando.sanjuan', '110', 'San Juan Del Prado Ángel Fernando', 'fernando.sanjuan@mess.com.mx', 49, 4, 27, '2016-01-11', 1, 3, '15', '17', 'PLANTA', 'M', '9605871589-5', 'SAPA8710014M0', 'SAPA871001HDFNRN05', 'ORH+', 110),
(40, 'alberto.mg@mess.com.mx', 'alberto.mg', '123', 'Morales Gallegos Jorge Alberto', 'alberto.mg@mess.com.mx', 53, 4, 28, '2015-03-05', 1, 3, '19', '18', 'PLANTA', 'M', '1409902519-0', 'MOGJ900821KRA', 'MOGJ900821HGTRLR09', 'BRH+', 93),
(41, 'ramon.almanza@mess.com.mx', 'ramon.almanzaaaaaa', '136', 'Almanza Sancen Jesús Ramón', 'ramon.almanza@mess.com.mx', 38, 4, 36, '2016-08-16', 0, 1, '45', '10', 'PLANTA', 'M', '1409900242-1', 'AASJ900721EP0', 'AASJ900721HGTLNS07', 'ORH+', 154),
(42, 'manuelr@mess.com.mx', 'manuelr', '142', 'Rosas García Luna José Manuel', 'manuelr@mess.com.mx', 56, 4, 38, '2016-11-08', 1, 3, '19', '12', 'PLANTA', 'M', '1607800023-5', 'ROGM800714TF5', 'ROGM800714HDFSRN07', 'ORH+', 127),
(43, 'jose.ruiz@mess.com.mx', 'jose.ruiz', '143', 'Ruiz Chavez José Apolinar', 'jose.ruiz@mess.com.mx', 1, 4, 7, '2016-12-01', 1, 1, '10', '-3', 'PLANTA', 'M', '1400802487-0', 'RUCA800428PTA', 'RUCA800428HTCZHP05', 'N/D', 115),
(44, 'alejandra.andrade@mess.com.mx', '19OCT1985', '147', 'Andrade Coronado Alejandra ', 'alejandra.andrade@mess.com.mx', 8, 4, 27, '2017-01-03', 1, 1, '110', '10', 'PLANTA', 'F', '1405852403-5', 'AACA851019HJ6', 'AACA851019MQTNRL03', 'ORH+', 120),
(45, 'luis.arias@mess.com.mx', 'luis.arias', '155', 'Arias Aguirre José Luis', 'luis.arias@mess.com.mx', 38, 4, 36, '2017-02-07', 1, 1, '45', '14', 'PLANTA', 'M', '6316934837-0', 'AIAL931015TU2', 'AIAL931015HSLRGS01', 'ORH+', 153),
(46, 'contabilidad@mess.com.mx', 'elConta21', '156', 'Santana Mejía Julio Cesar', 'contabilidad@mess.com.mx', 14, 4, 1, '2017-02-07', 1, 1, '2', '0', 'PLANTA', 'M', '0215852557-2', 'SAMJ850118UK4', 'SAMJ850118HDFNJL00', '', 190),
(47, 'fernanda.espino@mess.com.mx', 'fernanda.espino', '161', 'Espino Torres María Fernanda', 'fernanda.espino@mess.com.mx', 47, 4, 18, '2017-03-01', 1, 3, '521', '14', 'PLANTA', 'F', '1411935984-3', 'EITF931116UH3', 'EITF931116MQTSRR09', 'ORH+', 130),
(48, 'trans.fza@mess.com.mx', 'MESS2025', '162', 'Harrell Gonzalez Juan José', 'juan.harrell@mess.com.mx', 38, 4, 21, '2017-03-15', 1, 1, '14', '10', 'PLANTA', 'M', '4113945880-0', 'HAGJ940130CB3', 'HAGJ940130HSPRNN08', 'ORH+', 156),
(49, 'evita.glz.u@mess.com.mx', 'evita2026', '164', 'Eva Gonzalez Uribe', 'evita.glz.u@mess.com.mx', 65, 4, 1, '2017-04-03', 1, 1, '2', '13', 'PLANTA', 'F', '1401810331-8', 'GOUE810818DI3', 'GOUE810818MQTNRV08', '', 987654321),
(50, 'gisela.batule@mess.com.mx', 'gisela.batule', '167', 'Batule Naranjo Gisela ', 'gisela.batule@mess.com.mx', 60, 4, 1, '2017-04-10', 1, 1, '2', '16', 'PLANTA', 'F', '1410850732-9', 'BANG851112MP3', 'BANG851112MNETRS06', '', 141),
(51, 'jose.lara@mess.com.mx', 'jose.lara', '174', 'Lara de la Torre José Trinidad', 'jose.lara@mess.com.mx', 6, 3, 40, '2017-06-01', 1, 1, '324', '18', 'PLANTA', 'M', '1212947772-0', 'LATT7402175Q0', 'LATT740217HGTRRR03', 'ARH+', 151),
(52, 'atc.sfg@mess.com.mx', 'sfg.33.2017', '177', 'Servín Barrera Ana Zayi', 'atc.sfg@mess.com.mx', 37, 4, 36, '2017-06-01', 1, 1, '45', '21', 'PLANTA', 'F', '1212947772-0', 'SEBA9410047G2', 'SEBA941004MGTRRN09', 'ORH+', 122),
(53, 'victor.acosta@mess.com.mx', 'victor.acosta', '178', 'Acosta Castillo Victor Manuel', 'victor.acosta@mess.com.mx', 6, 4, 40, '2017-06-16', 1, 1, '324', '21', 'PLANTA', 'M', '4614958836-3', 'AOCV9511234UA', 'AOCV951123HGRCSC04', 'ORH+', 159),
(54, 'amram@mess.com.mx', 'Amry04lop', '183', 'López Ochoa Amram ', 'amram@mess.com.mx', 50, 4, 32, '2017-07-17', 1, 3, '19', '4', 'PLANTA', 'M', '1406910828-1', 'LOOA910401P79', 'LOOA910401HMNPCM03', 'ORH-', 308),
(55, 'luis.estrada@mess.com.mx', 'mess-app', '189', 'Estrada Paredes Luis Fidel', 'luis.estrada@mess.com.mx', 44, 4, 3, '2017-09-01', 1, 3, '15', '0', 'PLANTA', 'M', '0315897174-1', 'EAPL890627PX4', 'EAPL890627HGTSRS07', 'ORH+', 163),
(56, 'rogelio@mess.com.mx', 'rogelio2025', '191', 'Gutiérrez Cazares Rogelio ', 'rogelio@mess.com.mx', 7, 4, 10, '2017-09-01', 1, 1, '19', '-7', 'PLANTA', 'M', '1402720163-2', 'GUCR720915EA9', 'GUCR720915HDFTZG06', '', 162),
(57, 'fernando.gonzalez@mess.com.mx', 'Appfer192*', '192', 'González Aguado Fernando de Jesús', 'fernando.gonzalez@mess.com.mx', 35, 4, 3, '2017-09-01', 1, 1, '15', '0', 'PLANTA', 'M', '1486860014-0', 'GOAF8605237D3', 'GOAF860523HQTNGR02', 'ORH+', 81),
(58, 'jazmin.bautista@mess.com.mx', 'jazmin.bautista', '199', 'Bautista Nava Jazmin ', 'jazmin.bautista@mess.com.mx', 22, 4, 1, '2017-12-04', 1, 1, '2', '10', 'PLANTA', 'F', '7207881911-6', 'BANJ881007T36', 'BANJ881007MGRTVZ06', '', 179),
(59, 'rolando.carreon@mess.com.mx', 'Mess2021', '203', 'Carreón Castañeda Rolando ', 'rolando.carreon@mess.com.mx', 38, 8, 5, '2020-09-21', 1, 1, '11', '1', 'PLANTA', 'M', '4305850841-7', 'CACR851209FZ7', 'CACR851209HNLRSL08', 'ORH+', 193),
(60, 'orana.salcedo@mess.com.mx', 'orana.salcedo', '204', 'Salcedo Enriquez Orana Maheva', 'orana.salcedo@mess.com.mx', 8, 4, 29, '2017-12-11', 1, 1, '123', '17', 'PLANTA', 'F', '9002810905-7', 'SAEO810303P86', 'SAEO810303MDFLNR06', 'ORH+', 175),
(61, 'pablo.reyes@mess.com.mx', 'pablo.reyes', '206', 'Reyes Terán Juan Pablo', 'pablo.reyes@mess.com.mx', 38, 1, 36, '2017-12-11', 1, 3, '37', '15', 'PLANTA', 'M', '5108860958-4', 'RETJ8607305C8', 'RETJ860730HASYRN00', '', 191),
(62, 'adrian.castruita@mess.com.mx', 'Cancelar_cuenta', '212', 'Castruita Romero Adrián de Jesús', 'adrian.castruita@mess.com.mx', 47, 4, 20, '2018-01-29', 0, 3, '521', '22', 'PLANTA', 'M', '0218861566-6', 'CARA8609137A8', 'CARA860913HDGSMD05', 'BRH+', 183),
(63, 'ale.figueroa@mess.com.mx', 'ale.figueroa', '213', 'Figueroa Nieto Guadalupe Alejandra', 'ale.figueroa@mess.com.mx', 8, 6, 5, '2018-02-01', 1, 1, '226', '14', 'PLANTA', 'F', '1404842997-2', 'FING8407117Y8', 'FING840711MQTGTD02', 'ORH+', 160),
(64, 'ariadna@mess.com.mx', 'ariadna', '214', 'Mendoza Ibañez Ariadna Lizbeth', 'ariadna@mess.com.mx', 8, 4, 40, '2018-02-01', 1, 1, '19', '18', 'PLANTA', 'F', '2514956981-3', 'MEIA950423TR2', 'MEIA950423MGTNBR05', 'ORH+', 160),
(65, 'jlhernandez@mess.com.mx', 'jlhernandez', '215', 'Hernández Méndez Jorge Luis', 'jlhernandez@mess.com.mx', 59, 4, 24, '2018-03-05', 1, 3, '19', '22', 'PLANTA', 'M', '4103760221-0', 'HEMJ761111SN2', 'HEMJ761111HSPRNR00', '', 189),
(66, 'daniel.gonzalez@mess.com.mx', 'daniel.gonzalez', '222', 'Cuecuecha Gonzalez Daniel Aldair', 'daniel.gonzalez@mess.com.mx', 38, 4, 27, '2018-04-02', 1, 1, '110', '15', 'PLANTA', 'M', '6713941926-0', 'CUGD941019RK3', 'CUGD941019HVZCNN08', 'ORH+', 232),
(67, 'carmen.ls@mess.com.mx', 'carmen.ls', '225', 'López Sánchez María del Carmen', 'carmen.ls@mess.com.mx', 58, 4, 2, '2018-06-19', 1, 1, '71', '22', 'PLANTA', 'F', '2216944883-8', 'LOSC940715I91', 'LOSC940715MVZPNR01', 'ORH+', 382),
(68, 'adolfo.reyes@mess.com.mx', 'adolfo.reyes', '226', 'Reyes Durán Adolfo Isaí', 'adolfo.reyes@mess.com.mx', 38, 4, 5, '2018-07-16', 1, 3, '15', '0', 'PLANTA', 'M', '1412945011-1', 'REDA940501QP1', 'REDA940501HVZYRD06', 'ORH+', 218),
(69, 'ramon.jauregui@mess.com.mx', 'ramon.jauregui', '236', 'Jauregui Hernández Ramón ', 'ramon.jauregui@mess.com.mx', 6, 8, 40, '2018-10-01', 1, 1, '11', '20', 'PLANTA', 'M', '4300740202-7', 'JAHR740513BR2', 'JAHR740513HNLRRM03', 'ORH+', 212),
(70, 'misael.gutierrez@mess.com.mx', 'misael.gutierrez', '238', 'Gutiérrez Pacheco Misael ', 'misael.gutierrez@mess.com.mx', 43, 4, 18, '2018-10-16', 1, 1, '161', '11', 'PLANTA', 'M', '1412960612-6', 'GUPM960212ET0', 'GUPM960212HQTTCS05', 'ORH-', 216),
(71, 'teresa.iniguez@mess.com.mx', 'teresa.iniguez', '240', 'Iñiguez Alonzo Teresa de Jesús', 'teresa.iniguez@mess.com.mx', 6, 1, 40, '2018-10-22', 1, 1, '5555', '-2', 'PLANTA', 'F', '1207917049-9', 'IIAT911206831', 'IIAT911206MGTXLR07', 'ORH-', 213),
(72, 'osvaldo.lopez@mess.com.mx', 'osvaldo.lopez', '247', 'López Cruz Martín Osvaldo', 'osvaldo.lopez@mess.com.mx', 38, 10, 5, '2018-12-05', 1, 1, '288', '12', 'PLANTA', 'M', '4111935355-9', 'LOCM930223V22', 'LOCM930223HSPPRR00', 'ORH+', 284),
(73, 'ulises.alanis@mess.com.mx', 'ulises-mess', '260', 'Alanis Montes de Oca Ulises ', 'ulises.alanis@mess.com.mx', 38, 4, 5, '2019-02-18', 1, 1, '15', '19', 'PLANTA', 'M', '6316948215-3', 'AAMU941121KU3', 'AAMU941121HMNLNL05', 'ARH+', 244),
(74, 'julio.armadillo@mess.com.mx', 'armadillo.julio', '261', 'Armadillo Mercado Julio ', 'julio.armadillo@mess.com.mx', 38, 4, 5, '2019-02-18', 0, 1, '226', '7', 'PLANTA', 'M', '1409933678-7', 'AAMJ930515ET2', 'AAMJ930515HQTRRL08', 'ORH+', 245),
(75, 'aftermarket@mess.com.mx', 'Pleslie93!', '262', 'González López Pamela Leslie', 'aftermarket@mess.com.mx', 8, 4, 40, '2019-02-18', 1, 1, '263', '11', 'PLANTA', 'F', '1410931671-2', 'GOLP930408RC8', 'GOLP930408MDFNPM08', 'ARH+', 227),
(76, 'karen.hernandez@mess.com.mx', 'karen.hernandez', '263', 'Hernández Ruiz Ana Karen', 'karen.hernandez@mess.com.mx', 8, 4, 40, '2019-03-01', 1, 3, '19', '20', 'PLANTA', 'F', '4614961376-5', 'HERA960526DH4', 'HERA960526MQTRZN07', 'N/D', 230),
(77, 'sergio.martinez@mess.com.mx', 'Av210825', '264', 'Martínez Rojas Sergio Jeronimo', 'sergio.martinez@mess.com.mx', 58, 4, 40, '2019-03-01', 0, 1, '19', '18', 'PLANTA', 'M', '5414944184-9', 'MARS940830UJ9', 'MARS940830HHGRJR04', 'ORH+', 229),
(78, 'atc_fza@mess.com.mx', 'atc_fza', '267', 'Herrera Nuñez María Jessica', 'atc_fza@mess.com.mx', 9, 4, 21, '2019-03-06', 1, 1, '14', '13', 'PLANTA', 'F', '1404800152-4', 'HENJ801218DK6', 'HENJ801218MQTRXS07', 'ORH+', 231),
(79, 'alfonso.camacho@mess.com.mx', 'alfonso.camacho', '275', 'Camacho Alzaga Javier Alfonso', 'alfonso.camacho@mess.com.mx', 6, 4, 40, '2019-05-06', 1, 1, '324', '19', 'PLANTA', 'M', '1499801756-1', 'CAAJ800115RH6', 'CAAJ800115HDFMLV04', 'ABRH+', 239),
(80, 'pedro.martinez@mess.com.mx', '2806koruz', '276', 'Martínez Piña Pedro ', 'pedro.martinez@mess.com.mx', 61, 4, 32, '2019-05-02', 1, 3, '183', '12', 'PLANTA', 'M', '0319901354-5', 'MAPP901231SY4', 'MAPP901231HQTRXD09', 'ORH+', 312),
(81, 'j.arredondo@mess.com.mx', 'j.arredondo', '278', 'Arredondo Zuno Mayra Janneth', 'j.arredondo@mess.com.mx', 8, 4, 5, '2019-05-06', 1, 1, '11', '20', 'PLANTA', 'F', '5310851875-3', 'AEZM8508303Z7', 'AEZM850830MMNRNY06', '', 295),
(82, 'iztel.uribe@mess.com.mx', 'iztel.uribe', '279', 'Uribe Pacheco Karla Itzel', 'iztel.uribe@mess.com.mx', 6, 1, 40, '2019-05-05', 1, 1, '37', '20', 'PLANTA', 'F', '1213921001-2', 'UIPK920604TB9', 'UIPK920604MGRRCR08', '', 241),
(83, 'eduardo.trejo@mess.com.mx', 'Coster123', '283', 'Trejo Hernández Eduardo ', 'eduardo.trejo@mess.com.mx', 39, 4, 36, '2019-06-17', 1, 1, '45', '18', 'PLANTA', 'M', '2011952626-5', 'TEHE951210VA9', 'TEHE951210HDFRRD07', 'ORH+', 262),
(84, 'omar.corro@mess.com.mx', 'omar.corro', '288', 'Corro Fuentes Omar ', 'omar.corro@mess.com.mx', 30, 10, 40, '2019-08-19', 1, 3, '19', '12', 'PLANTA', 'M', '1406740205-8', 'COFO740204FR5', 'COFO740204HHGRNM07', 'ARH+', 256),
(85, 'arturo.coronel@mess.com.mx', 'cinout157', '290', 'Coronel Naranjo Arturo ', 'arturo.coronel@mess.com.mx', 15, 4, 25, '2019-08-20', 1, 1, '42', '8', 'PLANTA', 'M', '1413890530-3', 'CONA8906085QA', 'CONA890608HQTRRR03', 'BRH+', 158),
(86, 'daniel.guevara@mess.com.mx', 'daniel.guevara', '293', 'Guevara Calvillo Juan Daniel', 'daniel.guevara@mess.com.mx', 38, 1, 5, '2019-08-26', 1, 1, '206', '4', 'PLANTA', 'M', '2616949747-1', 'GUCJ940813RM2', 'GUCJ940813HASVLN02', '', 260),
(87, 'j.galeano@mess.com.mx', 'process.fza', '295', 'Galeano González Jazmín Montserrat', 'j.galeano@mess.com.mx', 10, 4, 21, '2019-09-02', 1, 1, '14', '11', 'PLANTA', 'F', '4915941138-5', 'GAGJ9405258J6', 'GAGJ940525MQTLNZ03', 'ORH+', 263),
(88, 'gerardo.delgado@mess.com.mx', 'gerardo.delgado', '298', 'Delgado Mendez Juan Gerardo', 'gerardo.delgado@mess.com.mx', 43, 10, 19, '2019-09-30', 1, 3, '288', '9', 'PLANTA', 'M', '4108904557-3', 'DEMJ900324RK5', 'DEMJ900324HSPLNN05', 'ORH+', 283),
(89, 'juan.guzman@mess.com.mx', 'juan.guzman', '306', 'Guzmán Ruiz Juan Antonio ', 'juan.guzman@mess.com.mx', 26, 8, 11, '2022-12-13', 1, 1, '15', '1', 'PLANTA', 'M', '516943255-5', 'GURJ940720PT5', 'GURJ940720HNLZZN01', '', 372),
(90, 'ingeniero.movil3@mess.com.mx', 'ingeniero.movil3', '307', 'López Perales María Isabel', 'ingeniero.movil3@mess.com.mx', 8, 1, 40, '2019-12-09', 0, 1, '1111', '11', 'PLANTA', 'F', '1817934520-4', 'LOPI93102857A', 'LOPI931028MASPRS03', 'ARH+', 173),
(91, 'adan@mess.com.mx', 'adan', '308', 'Enríquez Hernández Adán Alejandro', 'adan@mess.com.mx', 38, 4, 36, '2020-01-06', 1, 1, '45', '11', 'PLANTA', 'M', '4210940967-4', 'EIHA940813QZ7', 'EIHA940813HDFNRD04', 'ORH+', 286),
(92, 'ingeniero.movil5@mess.com.mx', 'ingeniero.movil5', '310', 'Arvizu Perrusquia Carlos Francisco', 'ingeniero.movil5@mess.com.mx', 38, 4, 29, '2020-02-05', 1, 1, '266', '14', 'PLANTA', 'M', '1412974721-9', 'AIPC970310F86', 'AIPC970310HQTRRR07', 'ARH+', 200),
(93, 'alfredo.paz@mess.com.mx', 'alfredo.paz', '311', 'Paz Mendoza Héctor Alfredo', 'alfredo.paz@mess.com.mx', 38, 8, 5, '2020-02-11', 1, 1, '11', '18', 'PLANTA', 'M', '3207908464-9', 'PAMH901106DG8', 'PAMH901106HCLZNC08', 'ORH+', 289),
(94, 'enrique.gonzalez@mess.com.mx', 'enrique.gonzalez', '317', 'González Galicia Enrique ', 'enrique.gonzalez@mess.com.mx', 6, 10, 40, '2020-03-19', 0, 1, '85', '15', 'PLANTA', 'M', '4186661078-9', 'GOGE660723NF0', 'GOGE660723HSPNLN02', 'ORH+', 171),
(95, 'guadalupe.suarez@mess.com.mx', 'maria.suarez', '318', 'Suárez Palomino María Guadalupe', 'guadalupe.suarez@mess.com.mx', 38, 4, 14, '2020-07-09', 1, 1, '161', '0', 'PLANTA', 'F', '1410940782-6', 'SUPG940102378', 'SUPG940102MGTRLD07', 'ORH+', 480),
(96, 'jhony.hernandez@mess.com.mx', 'jhony.hernandez', '321', 'Hernández Mendoza Jhony Raymundo', 'jhony.hernandez@mess.com.mx', 38, 4, 21, '2020-09-01', 1, 1, '14', '1', 'PLANTA', 'M', '3815971764-4', 'HEMJ970719BH3', 'HEMJ970719HHGRNH00', 'ORH+', 296),
(97, 'martin.becerra@mess.com.mx', 'martin.becerra', '324', 'Becerra Medina Martín ', 'martin.becerra@mess.com.mx', 57, 4, 40, '2020-10-12', 1, 3, '19', '12', 'PLANTA', 'M', '1498820282-7', 'BEMM820328EK9', 'BEMM820328HQTCDR08', 'ARH+', 292),
(98, 'alberto.olguin@mess.com.mx', 'alberto.olguin', '327', 'Olguin Barrera Luis Alberto', 'alberto.olguin@mess.com.mx', 43, 4, 18, '2020-12-08', 1, 1, '161', '11', 'PLANTA', 'M', '1616953550-3', 'OUBL950813RX9', 'OUBL950813HHGLRS01', 'ORH+', 298),
(99, 'arely.montoya@mess.com.mx', 'arely.montoya', '328', 'Montoya Casas Arely ', 'arely.montoya@mess.com.mx', 28, 4, 1, '2021-02-08', 1, 1, '2', '7', 'PLANTA', 'F', '1408894295-9', 'MOCA8901101C1', 'MOCA890110MQTNSR07', 'ARH+', 301),
(100, 'cesar.hernandez@mess.com.mx', 'cesar.hernandez', '329', 'Hernández Vite Cesar Armando', 'cesar.hernandez@mess.com.mx', 38, 4, 29, '2021-06-16', 0, 1, '123', '16', 'PLANTA', 'M', '2016926638-2', 'HEVC920716J97', 'HEVC920716HHGRTS03', 'ORH+', 311),
(101, 'hector.losoyo@mess.com.mx', 'hector.losoyo', '333', 'Losoyo Vega Héctor Julián', 'hector.losoyo@mess.com.mx', 38, 4, 0, '2021-01-05', 0, 3, '9999', '8', 'PLANTA', 'M', '1404851563-0', 'LOVH8501234k0', 'LOVH850123HDFSGC03', '', 299),
(102, 'jose.munoz@mess.com.mx', 'jose.munoz', '335', 'Muñoz Ugalde José de Jesús', 'jose.munoz@mess.com.mx', 38, 4, 27, '2021-01-11', 1, 1, '110', '14', 'PLANTA', 'M', '1413986279-2', 'MUUJ980121CA9', 'MUUJ980121HQTXGS00', 'ARH+', 304),
(103, 'florangeli@mess.com.mx', 'florangeli', '337', 'Vazquez Baltazar Florangeli de Jesus', 'florangeli@mess.com.mx', 6, 7, 40, '2021-09-06', 1, 1, '324', '6', 'PLANTA', 'F', '5508891124-7', 'VABF891225UD3', 'VABF891225MNTZLL01', 'ORH+', 319),
(104, 'alfredo.robles@mess.com.mx', 'alfredo.robles', '341', 'Robles Silva Luis Alfredo', 'alfredo.robles@mess.com.mx', 38, 4, 35, '2021-10-04', 1, 1, '14', '11', 'PLANTA', 'M', '1410947636-7', 'ROSL941227UT2', 'ROSL941227HQTBLS06', 'ORH+', 321),
(105, 'logistica@mess.com.mx', 'logistica', '342', 'Galvan Alvarez Edgar', 'logistica@mess.com.mx', 63, 4, 25, '2021-10-04', 1, 1, '42', '7', 'PLANTA', 'M', '2894740480-3', 'GAAE741010S92', 'GAAE741010HMCLLD00', 'ORH+', 112),
(106, 'logistica.slp@mess.com.mx', 'sx2244b.', '344', 'Cazares Zapata Eder Geovanni ', 'logistica.slp@mess.com.mx', 63, 10, 25, '2021-10-18', 1, 1, '288', '8', 'PLANTA', 'M', '6416930975-0', 'CAZE931205CT4', 'CAZE931205HSPZPD06', 'ORH+', 322),
(107, 'adrian.olvera@mess.com.mx', 'adrian.olvera', '347', 'Olvera Alegría Juan Adrián', 'adrian.olvera@mess.com.mx', 25, 4, 30, '2021-12-01', 1, 1, '15', '16', 'PLANTA', 'M', '1411941363-2', 'OEAJ941220LU7', 'OEAJ941220HQTLLN02', 'ORH+', 99999),
(108, 'facturacion@mess.com.mx', 'facturacion', '348', 'Meza Hernández Teresa', 'facturacion@mess.com.mx', 28, 4, 1, '2021-12-01', 1, 1, '2', '3', 'PLANTA', 'F', '7205861754-8', 'MEHT860312MZ2', 'MEHT860312MGRZRR01', 'ORH+', 327),
(109, 'eduardo.prado@mess.com.mx', 'eduardo.prado', '350', 'Prado de Leon David Eduardo', 'eduardo.prado@mess.com.mx', 43, 10, 5, '2021-12-20', 1, 1, '288', '0', 'PLANTA', 'M', '4112924443-4', 'PALD920918MJ0', 'PALD920918HSPRNV05', 'ARH+', 329),
(110, 'guillermo.monsivais@mess.com.mx', 'guillermo.monsivais', '356', 'Monsiváis Cepeda Guillermo Alonso', 'guillermo.monsivais@mess.com.mx', 6, 8, 40, '2022-01-17', 0, 1, '5555', '14', 'PLANTA', 'M', '4302841374-8', 'MOCG840913R15', 'MOCG840913HNLNPL02', '', 330),
(111, 'karen.canul@mess.com.mx', 'karen.canul', '357', 'Canul Camacho Karen Militza', 'karen.canul@mess.com.mx', 8, 4, 36, '2022-01-19', 0, 1, '45', '8', 'PLANTA', 'F', '7416990688-5', 'CACK990610TI7', 'CACK990610MTCNMR08', 'ORH+', 318),
(112, 'edgar.vargas@mess.com.mx', 'edgar.vargas', '359', 'Vargas Hernández Edgar', 'edgar.vargas@mess.com.mx', 43, 10, 19, '2022-01-24', 1, 1, '298', '7', 'BAJA', 'M', '0516965009-9', 'VAHE960212HC8', 'VAHE960212HSPRRD02', '', 334),
(113, 'pedro.velazquez@mess.com.mx', 'pedro.velazquez', '360', 'Velázquez Vidal Pedro Luis ', 'pedro.velazquez@mess.com.mx', 38, 4, 20, '2022-01-24', 1, 3, '521', '7', 'PLANTA', 'M', '1313996577-9', 'VEVP9904141S8', 'VEVP990414HMNLDD07', 'ORH+', 333),
(114, 'Lorena.lopez@mess.com.mx', 'Lorena.lopez', '361', 'López Vergara Lorena Guadalupe', 'Lorena.lopez@mess.com.mx', 43, 10, 39, '2022-02-01', 1, 1, '5555', '12', 'PLANTA', 'F', '0316889972-6', 'LOVL880824598', 'LOVL880824MNTPRR07', '', 335),
(115, 'carlos.martinez@mess.com.mx', 'carlos.martinez', '366', 'Martínez Tinoco Carlos Uriel', 'carlos.martinez@mess.com.mx', 38, 4, 36, '2022-02-14', 1, 1, '45', '12', 'PLANTA', 'M', '3416981203-8', 'MATC980907B65', 'MATC980907HVZRNR00', '', 341),
(116, 'osiel.pardo@mess.com.mx', 'osiel.pardo', '374', 'Pardo Reyes Osiel Amador', 'osiel.pardo@mess.com.mx', 6, 10, 40, '2025-05-08', 1, 1, '288', '14', 'PLANTA', 'M', '4113932243-6', 'PARO931205278', 'PARO931205HSPRYS03', 'ORH-', 349),
(117, 'luz.sanchez@mess.com.mx', 'luz.sanchez', '376', 'Sánchez Rubio María de la Luz', 'luz.sanchez@mess.com.mx', 8, 4, 40, '2022-03-22', 1, 1, '324', '9', 'PLANTA', 'F', '1406911428-9', 'SARL910227AZ9', 'SARL910227MQTNBZ09', 'BRH+', 346),
(118, 'alejandro.sanchez@mess.com.mx', 'alejandro.sanchez', '377', 'Sanchez Lopez Jesús Alejandro ', 'alejandro.sanchez@mess.com.mx', 38, 4, 36, '2022-03-23', 1, 1, '45', '7', 'PLANTA', 'M', '5116990035-7', 'SALJ990805F39', 'SALJ990805HCSNPS01', 'ORH+', 350),
(119, 'alba@mess.com.mx', 'alba', '378', 'Hernández Trinidad Alba Lizeth', 'alba@mess.com.mx', 42, 4, 38, '2022-04-04', 1, 1, '142', '9', 'PLANTA', 'F', '3216999238-8', 'HETA990219467', 'HETA990219MVZRRL09', 'ORH+', 353),
(120, 'francisco.martinez@mess.com.mx', 'Olaf1974', '380', 'Martinez Sanchez Juan Francisco ', 'francisco.martinez@mess.com.mx', 6, 10, 40, '2022-07-11', 1, 1, '85', '-4', 'BAJA', 'M', '4190743492-6', 'MASJ7403246S3', 'MASJ740324HNLRNN02', '', 352),
(121, 'oscar3.parra@mess.com.mx', 'oscar3.parra', '383', 'Parra Flores Oscar III', 'oscar3.parra@mess.com.mx', 6, 9, 40, '2022-05-04', 1, 1, '5555', '14', 'PLANTA', 'M', '3295754196-7', 'PAFO751202S42', 'PAFO751202HCLRLS05', '', 357),
(122, 'josefina@mess.com.mx', 'josefina', '385', 'Sampogna Valdez Josefina', 'josefina@mess.com.mx', 8, 8, 40, '2022-06-06', 1, 1, '11', '14', 'PLANTA', 'F', '4398780660-1', 'SAVJ780421KK5', 'SAVJ780421MNLMLS02', 'ARH+', 358),
(123, 'cuentasdegastos@mess.com.mx', 'cuentasdegastos', '386', 'Torres Lezama Ana Victoria', 'cuentasdegastos@mess.com.mx', 20, 4, 1, '2022-07-04', 1, 1, '2', '-1', 'PLANTA', 'F', '2805860693-1', 'TOLA861202DU7', 'TOLA861202MDFRZN07', 'BRH+', 310),
(124, 'maria.ayala@mess.com.mx', 'maria.ayala', '390', 'Ayala Cerecedo Ana María', 'maria.ayala@mess.com.mx', 43, 4, 15, '2022-08-16', 1, 1, '487', '-3', 'PLANTA', 'F', '7216003237-0', 'AACA001013QB7', 'AACA001013MMCYRNA1', 'ORH+', 362),
(125, 'ociel@mess.com.mx', 'ociel', '397', 'Rodriguez Vega Ociel Alejandro', 'ociel@mess.com.mx', 43, 4, 29, '2022-09-26', 1, 1, '5555', '4', 'PLANTA', 'M', '0314986586-1', 'ROVO9805066D9', 'ROVO980506HQTDGC05', 'ARH+', 426),
(126, 'jose.reynoso@mess.com.mx', 'jose.reynoso', '399', 'Reynoso Noguez Jose Maria', 'jose.reynoso@mess.com.mx', 40, 8, 5, '2022-10-11', 1, 1, '11', '2', 'TEMPORAL', 'M', '3700821909-1', 'RENM820113PK9', 'RENM820113HDFYGR04', 'ORH+', 368),
(127, 'fernanda.hernandez@mess.com.mx', 'rrhh2025', '403', 'Hernández Manrique María Fernanda', 'fernanda.hernandez@mess.com.mx', 5, 4, 34, '2022-11-16', 1, 3, '19', '10', 'PLANTA', 'F', '1408890684-8', 'HEMF891029QJ3', 'HEMF891029MDFRNR04', 'ORH+', 265),
(128, 'emmanuel.vizcaya@mess.com.mx', 'emmanuel.vizcaya', '404', 'Vizcaya Hernández Emmanuel ', 'emmanuel.vizcaya@mess.com.mx', 43, 4, 1, '2022-11-24', 1, 1, '360', '2', 'PLANTA', 'M', '315003179-1', 'VHE000401PM2', 'VHE000401HQTZRMA0', 'ORH+', 371),
(129, 'guillermo.cruz@mess.com.mx', 'guillermo.cruz', '406', 'Cruz Dominguez Guillermo ', 'guillermo.cruz@mess.com.mx', 43, 4, 21, '2023-01-02', 1, 1, '14', '3', 'PLANTA', 'M', '7816990647-2', 'CUDG990705G49', 'CUDG990705HVZRML07', 'ORH+', 374),
(130, 'maria.luna@mess.com.mx', 'maria.luna', '407', 'De Jesús Luna María José', 'maria.luna@mess.com.mx', 43, 4, 18, '2023-01-16', 1, 1, '5555', '4', 'PLANTA', 'F', '1411952534-4', 'JELJ950516QP6', 'JELJ950516MQRSNS00', 'ORH+', 377),
(131, 'julio.saldivar@mess.com.mx', 'julio.saldivar', '410', 'Saldívar Acuña Julio Cesar', 'julio.saldivar@mess.com.mx', 6, 8, 0, '2023-01-23', 1, 1, '5555', '2', 'PLANTA', 'M', '519977898-5', 'SAAJ971218BQ1', 'SAAJ971218HNLLCL01', '', 381),
(132, 'omar.aviles@mess.com.mx', 'omar.aviles', '411', 'Avilés Rodríguez Omar Yoseph ', 'omar.aviles@mess.com.mx', 43, 10, 39, '2023-01-23', 1, 1, '288', '12', 'PLANTA', 'M', '2413985105-9', 'AIRO981012NA8', 'AIRO981012HSPVDM08', '', 439),
(133, 'perla.rocha@mess.com.mx', 'perla.rocha', '412', 'Rocha Juárez Perla Sarahí ', 'perla.rocha@mess.com.mx', 8, 10, 0, '2023-01-24', 1, 1, '5555', '7', 'PLANTA', 'F', '3517973628-1', 'ROJP9709253G2', 'ROJP970925MSPCRR05', 'ORH+', 376),
(134, 'paola.martinez@mess.com.mx', 'paola.martinez', '413', 'Martínez Gutiérrez Paola de Jesús ', 'paola.martinez@mess.com.mx', 8, 10, 40, '2023-02-01', 1, 1, '5555', '8', 'PLANTA', 'F', '4113950808-3', 'MAGP9504064D7', 'MAGP950406MSPRTL01', '', 378),
(135, 'joyce@mess.com.mx', 'joyce', '414', 'Cabello Corona Joyce ', 'joyce@mess.com.mx', 43, 4, 29, '2023-02-01', 1, 1, '123', '9', 'PLANTA', 'F', '5815954623-1', 'CACJ950224U83', 'CACJ950224MQTBRY01', 'ARH+', 380),
(136, 'javier.castillo@mess.com.mx', 'javier.castillo', '415', 'Castillo Marquez Javier Alberto ', 'javier.castillo@mess.com.mx', 43, 4, 29, '2023-02-07', 1, 1, '123', '0', 'PLANTA', 'M', '1216952584-2', 'CAMJ9511178DA', 'CAMJ951117HMSSRV03', 'ORH+', 396),
(137, 'cuentasporcobrar@mess.com.mx', 'cuentasporcobrar2025', '416', 'Trejo Sánchez María Guadalupe ', 'cuentasporcobrar@mess.com.mx', 21, 4, 1, '2023-02-07', 1, 1, '2', '0', 'PLANTA', 'F', '1497790951-5', 'TESG7910139C7', 'TESG791013MQTRND08', 'ORH+', 166),
(138, 'silvia.figueroa@mess.com.mx', 'silvia.figueroa', '420', 'Figueroa Camacho Silvia Cristina ', 'silvia.figueroa@mess.com.mx', 8, 8, 0, '2023-03-01', 1, 1, '11', '0', 'PLANTA', 'F', '3292717584-7', 'FICS710502D68', 'FICS710502MCLGML06', '', 385),
(139, 'jose.morales@mess.com.mx', 'jose.morales', '424', 'Morales Gómez José Yoyarib ', 'jose.morales@mess.com.mx', 38, 4, 29, '2023-03-16', 1, 1, '123', '0', 'PLANTA', 'M', '2516984080-5', 'MOGY980103TX8', 'MOGY980103HQTRNY02', 'ARH-', 395),
(140, 'hernan.lopez@mess.com.mx', 'hernan.lopez', '426', 'López Hernández Hernán ', 'hernan.lopez@mess.com.mx', 11, 4, 7, '2023-03-27', 1, 1, '10', '0', 'PLANTA', 'M', '1411966378-0', 'LOHH960122BT1', 'LOHH960122HQTPRR06', '', 389),
(141, 'eduardo.corral@mess.com.mx', 'eduardo.corral', '427', 'Corral Olvera Eduardo Alejandro ', 'eduardo.corral@mess.com.mx', 38, 1, 36, '2023-04-03', 1, 1, '206', '0', 'PLANTA', 'M', '5115000227-0', 'COOE000409UQ0', 'COOE000409HASRLDA1', 'ARH+', 390),
(142, 'ivan.martinez@mess.com.mx', 'ivan.martinez', '428', 'Martínez Arias Ivan ', 'ivan.martinez@mess.com.mx', 43, 4, 18, '2023-04-03', 1, 3, '161', '10', 'PLANTA', 'M', '314986573-9', 'MAAI980909HK1', 'MAAI980909HMNRRV08', 'ARH+', 391),
(143, 'itan.nava@mess.com.mx', 'itan.nava', '432', 'Nava Díaz Itan Uriel ', 'itan.nava@mess.com.mx', 38, 4, 29, '2023-05-08', 1, 1, '123', '0', 'PLANTA', 'M', '913018696-9', 'NADI0110032S3', 'NADI11003HDFVZTA5', '', 370),
(144, 'lilibeth.herrera@mess.com.mx', 'lilibeth.herrera', '435', 'Herrera Carreto Lilibeth Miren ', 'lilibeth.herrera@mess.com.mx', 4, 4, 21, '2023-05-16', 1, 1, '14', '0', 'PLANTA', 'F', '322976976-9', 'HECL970828MD6', 'HECL970828MGRRRL01', '', 397),
(145, 'juanita.estrella@mess.com.mx', 'juanita.estrella', '437', 'Cruz Estrella María Juana', 'juanita.estrella@mess.com.mx', 8, 4, 21, '2023-05-16', 1, 1, '14', '0', 'PLANTA', 'F', '1413976383-4', 'CUEJ971008QF7', 'CUEJ971008MQTRSN07', '', 400),
(146, 'yair.gomez@mess.com.mx', 'yair.gomez', '438', 'Gomez Carranza Jasiel Yair', 'yair.gomez@mess.com.mx', 35, 4, 3, '2023-05-16', 1, 1, '189', '0', 'PLANTA', 'M', '215922978-6', 'GOCJ920723K99', 'GOCJ920723HHGMRS04', '', 401),
(147, 'christian.resendiz@mess.com.mx', 'christian.resendiz', '439', 'Resendiz Valadez Christian Erick', 'christian.resendiz@mess.com.mx', 6, 8, 40, '2023-06-05', 1, 1, '11', '0', 'PLANTA', 'M', '4303852713-1', 'REVC850629PT6', 'REVC850629HNLSLH09', '', 404),
(148, 'armando.nolasco@mess.com.mx', 'armando.nolasco', '440', 'Nolasco Rodríguez Armando  ', 'armando.nolasco@mess.com.mx', 41, 4, 1, '2023-06-12', 1, 1, '123', '0', 'PLANTA', 'M', '3291739634-6', 'NORA730619K79', 'NORA730619HCLLDR02', '', 406),
(149, 'marco.mandujano@mess.com.mx', 'marco.mandujano', '442', 'Mandujano Gómez Marco Antonio ', 'marco.mandujano@mess.com.mx', 43, 4, 29, '2023-06-26', 1, 1, '123', '0', 'PLANTA', 'M', '1412934765-5', 'MAGM931006LY7', 'MAGM931006HQTNMR05', '', 427),
(150, 'jesus.ruiz@mess.com.mx', 'jesus.ruiz', '446', 'Ruiz Samano Jesús Antonio ', 'jesus.ruiz@mess.com.mx', 38, 8, 36, '2023-07-06', 1, 1, '11', '0', 'PLANTA', 'M', '2516999715-9', 'RUSJ991121CH1', 'RUSJ991121HTSZMS00', '', 415),
(151, 'jesus.sanchez@mess.com.mx', 'jesus.sanchez', '447', 'Sánchez Chávez Jesús', 'jesus.sanchez@mess.com.mx', 38, 8, 36, '2023-07-06', 1, 1, '11', '0', 'PLANTA', 'M', '7216000346-2', 'SACJ000104375', 'SACJ000104HTSNHSA4', 'ARH+', 414),
(152, 'jaime.salinas@mess.com.mx', 'jaime.salinas', '448', 'Salinas Gómez Jaime', 'jaime.salinas@mess.com.mx', 38, 4, 3, '2023-07-06', 1, 1, '189', '0', 'PLANTA', 'M', '613003345-6', 'SAGJ000603NI7', 'SAGJ000603HCSLMMA1', '', 412),
(153, 'aaron.sanchez@mess.com.mx', 'aaron.sanchez', '451', 'Sánchez Nataren Jorge Aaron', 'aaron.sanchez@mess.com.mx', 38, 4, 5, '2023-07-18', 1, 1, '226', '0', 'PLANTA', 'M', '613004788-6', 'SAGJ000603NI7', 'SAGJ000603HCSLMMA1', '', 420),
(154, 'elena.mendez@mess.com.mx', '2milhoras', '452', 'Méndez Salazar María Elena', 'elena.mendez@mess.com.mx', 8, 4, 2, '2023-07-24', 1, 1, '71', '0', 'PLANTA', 'F', '5308873382-8', 'MESE870221JW9', 'MESE870221MDFNLL09', '', 418),
(155, 'beatriz.Portilla@mess.com.mx', 'beatriz.Portilla', '453', 'Portilla Mena Beatriz Gabriela', 'beatriz.Portilla@mess.com.mx', 8, 5, 0, '2023-08-01', 1, 1, '324', '0', 'PLANTA', 'F', '1212925404-6', 'POMB9206264F0', 'POMB920626MPLRNT02', '', 419),
(156, 'leticia.vazquez@mess.com.mx', 'leticia.vazquez', '455', 'Vazquez Granados Leticia', 'leticia.vazquez@mess.com.mx', 6, 4, 40, '2023-08-09', 1, 1, '324', '0', 'PLANTA', 'F', '3903820897-9', 'VAGL820904GH4', 'VAGL820904MMCZRT06', '', 421),
(157, 'facturacion2@mess.com.mx', 'Mess2025H', '456', 'Arroyo Hernández Hector', 'facturacion2@mess.com.mx', 3, 4, 1, '2023-09-04', 1, 1, '2', '0', 'PLANTA', 'M', '1511901464-3', 'AOHH900422194', 'AOHH900422HMSRRC08', '', 416),
(158, 'adal.beltran@mess.com.mx', 'adal.beltran', '457', 'Beltrán Montaño Cesar Adal', 'adal.beltran@mess.com.mx', 43, 4, 21, '2023-09-04', 1, 1, '14', '0', 'PLANTA', 'M', '1413985617-4', 'BEMC9804023GO', 'BEMC980402HQTLNS01', '', 423),
(159, 'mayan.nunez@mess.com.mx', 'mayan.nunez', '458', 'Nuñez Rubert Mayan Arianna ', 'mayan.nunez@mess.com.mx', 8, 10, 0, '2023-10-02', 1, 1, '85', '0', 'PLANTA', 'F', '4111933754-5', 'NURM930829IV2', 'NURM930829MSPXBY03', '', 428),
(160, 'bryan.landeros@mess.com.mx', 'bryan.landeros', '459', 'Landeros Alamilla Bryan Modesto ', 'bryan.landeros@mess.com.mx', 38, 4, 36, '2023-10-10', 1, 1, '45', '0', 'PLANTA', 'M', '1413986036-6', 'LAAB981010LB3', 'LAAB981010HDFNLR05', '', 430),
(161, 'maricarmen.cordova@mess.com.mx', 'Maryka07.', '460', 'Cordova Gamboa Maricarmen ', 'maricarmen.cordova@mess.com.mx', 8, 10, 0, '2023-10-25', 1, 1, '85', '0', 'PLANTA', 'F', '4112934410-1', 'COGM930207SF8', 'COGM930207MSPRMR06', '', 431),
(245, 'alberto.contreras@mess.com.mx', 'alberto.contreras', '556', 'Jose Alberto Contreras Arredondo', 'alberto.contreras@mess.com.mx', 43, 4, 29, '2025-12-22', 1, 1, '123', '0', 'PLANTA', 'M', '0', '0', '0', '0', 529),
(163, 'eber.sarabia@mess.com.mx', 'eber.sarabia', '466', 'Sarabia Valdez Eber Emiliano ', 'eber.sarabia@mess.com.mx', 13, 8, 12, '2023-12-01', 0, 1, '11', '0', 'PLANTA', 'M', '28170289350', 'SAVE020921TM3', 'SAVE020921HNLRLBA2', 'ORH+', 9999),
(164, 'hector.ortiz@mess.com.mx', 'hector.ortiz', '467', 'Ortiz Santiago Hector Uriel ', 'hector.ortiz@mess.com.mx', 38, 4, 22, '2023-12-04', 1, 1, '360', '0', 'PLANTA', 'M', '41129441477', 'OISH9407119F3', 'OISH940711HSPRNC08', 'ORH+', 435),
(165, 'gregorio.garcia@mess.com.mx', 'gregorio.garcia', '468', 'Garcia Baeza Juan Gregorio ', 'gregorio.garcia@mess.com.mx', 38, 4, 13, '2023-12-04', 1, 1, '45', '0', 'PLANTA', 'M', '35189949627', 'GABJ9911097M7', 'GABJ991109HGTRZN03', '', 424),
(166, 'carlos.robledo@mess.com.mx', 'carlos.robledo', '469', 'Robledo Calvillo  Carlos Manuel Efraín ', 'carlos.robledo@mess.com.mx', 6, 8, 40, '2023-12-12', 1, 1, '0', '0', 'PLANTA', 'M', '32119236159', 'ROCC920705LY2', 'ROCC920705HCLBLR06', 'ARH+', 436),
(167, 'alejandro.palomino@mess.com.mx', 'alejandro.palomino', '470', 'Palomino García Alejandro Ulises ', 'alejandro.palomino@mess.com.mx', 43, 10, 9, '2023-12-18', 1, 1, '298', '0', 'PLANTA', 'M', '24139812812', 'PAGA980216UK3', 'PAGA980216HSPLRL05', 'ORH-', 437),
(168, 'zaira.balderrama@mess.com.mx', 'zaira.balderrama', '472', 'Balderrama Ramirez Zaira Jimena ', 'zaira.balderrama@mess.com.mx', 12, 4, 29, '2024-01-11', 1, 1, '123', '0', 'PLANTA', 'F', '5220183635', 'BARZ010108B37', 'BARZO10108MQTLMRA6', '', 429),
(169, 'beatriz.martinez@mess.com.mx', 'beatriz.martinez', '474', 'Martínez Beltrán Beatriz ', 'beatriz.martinez@mess.com.mx', 8, 4, 2, '2024-02-01', 1, 1, '71', '0', 'PLANTA', 'F', '38169503026', 'MABB951207HQ3', 'MABB951207MGRRLT05', 'ORH-', 445),
(170, 'ventas.gdl@mess.com.mx', 'ventas.gdl', '475', 'Hernández Gutiérrez Melani Nicte ', 'ventas.gdl@mess.com.mx', 6, 2, 40, '2024-02-01', 1, 1, '37', '0', 'PLANTA', 'F', '45119434079', 'HEGM9401022V8', 'HEGM940102MDFRTL11', 'ORH+', 531),
(171, 'ossiel.gallardo@mess.com.mx', 'ossiel.gallardo', '478', 'Gallardo Cruz Ossiel de Jesús ', 'ossiel.gallardo@mess.com.mx', 38, 4, 5, '2024-02-01', 1, 1, '226', '0', 'PLANTA', 'M', '78169958325', 'GACO990522CBA', 'GACO990522HVZLRS09', 'ORH+', 459),
(172, 'maricruz.hernandez@mess.com.mx', 'maricruz.hernandez', '479', 'Hernández Muñoz Maricruz de Jesús ', 'maricruz.hernandez@mess.com.mx', 8, 1, 0, '2024-02-12', 1, 1, '0', '0', 'PLANTA', 'F', '51159720633', 'HEMM9708184D0', 'HEMM970818MASRXR07', 'ORH+', 447),
(173, 'cotizaciones1@mess.com.mx', 'cotizaciones1', '559', 'Paulina Lilian Olvera Hernández', 'cotizaciones1@mess.com.mx', 8, 4, 40, '2026-01-22', 1, 1, '324', '0', 'PLANTA', 'F', '38190133702', 'CATA0101144J1', 'CATA010114MVZNRBA8', 'ORH+', 141),
(174, 'Braulio.jaime@mess.com.mx', 'Braulio.jaime', '483', 'Jaime Sánchez  Braulio', 'Braulio.jaime@mess.com.mx', 38, 4, 18, '2024-03-16', 1, 1, '0', '0', 'TEMPORAL', 'M', '26190209903', 'JASB0202204B1', 'JASB020220HQTMNRA0', '', 450),
(175, 'monserrat.lopez@mess.com.mx', 'monserrat.lopez', '484', 'Dulce Monserrat López Elizondo ', 'monserrat.lopez@mess.com.mx', 8, 4, 40, '2024-03-19', 1, 1, '263', '0', 'PLANTA', 'F', '73150090501', 'LOED000912S52', 'LOED000912MQTPLLA9', 'ORH+', 451),
(176, 'felipe.martinez@mess.com.mx', 'SAleen_27', '485', 'Martínez Moriel Felipe ', 'felipe.martinez@mess.com.mx', 38, 4, 28, '2024-05-20', 1, 1, '123', '0', 'TEMPORAL', 'M', '1408891936-1', 'MAMF890327D83', 'MAMF890327HSLRRL02', '', 82),
(177, 'Gerardo.lopez@mess.com.mx', 'gerardo.lopez', '486', 'López Morales Gerardo Francisco ', 'Gerardo.lopez@mess.com.mx', 38, 4, 22, '2024-04-17', 1, 1, '0', '0', 'TEMPORAL', 'M', '1817998359-0', 'LOMG990829HL5', 'LOMG990829HCSPRR03', 'ARH+', 453),
(178, 'angeles.pacheco@mess.com.mx', 'angeles.pacheco', '487', 'Pacheco Pacheco María de los Angeles ', 'angeles.pacheco@mess.com.mx', 38, 4, 31, '2024-05-02', 1, 3, '521', '0', 'TEMPORAL', 'F', '27170242807', 'PAPA020107DP5', 'PAPA020107MQTCCNA4', '', 454),
(179, 'ramiro.garcia@mess.com.mx', 'ramiro.garcia', '488', 'Ramiro García Noguez ', 'ramiro.garcia@mess.com.mx', 6, 4, 0, '2024-05-06', 1, 1, '324', '0', 'TEMPORAL', 'M', '14078306041', 'GANR830627U41', 'GANR830627HQTRGM09', 'ORH+', 455),
(180, 'atencion.sfg@mess.com.mx', 'atencion.sfg', '489', 'Maria Fernanda Hurtado García ', 'atencion.sfg@mess.com.mx', 8, 4, 36, '2024-05-13', 1, 1, '45', '0', 'TEMPORAL', 'F', '14129209285', 'HUGF920813IP1', 'HUGF920813MQTRRR08', '', 456),
(181, 'gamaliel@mess.com.mx', 'gamaliel', '490', 'Lugo Rojas Gamaliel', 'gamaliel@mess.com.mx', 38, 4, 28, '2024-05-20', 1, 1, '123', '0', 'TEMPORAL', 'M', '64170240143', 'LURG020727K66', 'LURG020727HHGGJMA8', '', 473),
(182, 'angel.bonilla@mess.com.mx', 'angel.bonilla', '491', 'Angel Adolfo Bonilla Meza ', 'angel.bonilla@mess.com.mx', 38, 4, 21, '2024-06-10', 1, 1, '14', '0', 'TEMPORAL', 'M', '75170251302', 'BOMA020530GC6', 'BOMA020530HTLNZNA4', 'ARH+', 457),
(183, 'logistica2@mess.com.mx', 'logistica2', '492', 'Edgar Jonathan Maya Sosa', 'logistica2@mess.com.mx', 33, 4, 25, '2024-06-17', 1, 1, '42', '0', 'PLANTA', 'M', '14008363906', 'MASE831217T83', 'MASE831217HQTYSD15', '', 458),
(184, 'tomas@mess.com.mx', 'Gudi2312', '494', 'Pérez Gudiño Tomás ', 'tomas@mess.com.mx', 34, 4, 21, '2024-07-03', 1, 1, '14', '0', '0', 'M', '25170170788', 'GUPT011223CV5', 'GUPT011223HQTDRMA7', '', 461),
(185, 'maria.sanchez@mess.com.mx', 'maria.sanchez', '495', 'Sánchez García Maria Fernanda ', 'maria.sanchez@mess.com.mx', 43, 4, 15, '2024-07-03', 1, 1, '360', '0', '0', 'F', '19170170385', 'SAGF0110116F7', 'SAGF011011MQTNRRA7', 'ORH+', 460),
(186, 'susana.lopez@mess.com.mx', 'susana.lopez', '496', 'López Ramírez Susana', 'susana.lopez@mess.com.mx', 8, 1, 40, '2024-07-16', 1, 1, '37', '0', '0', 'F', '', '', '', '', 463),
(187, 'andres.mateos@mess.com.mx', 'andres.mateos', '497', 'Luis Andres Mateo Santiago', 'andres.mateos@mess.com.mx', 38, 4, 27, '2024-07-22', 1, 1, '0', '0', '0', 'M', '', '', '', '', 467),
(188, 'sebastian.angulo@mess.com.mx', 'sebastian.angulo', '498', 'Sebastián Angulo González', 'sebastian.angulo@mess.com.mx', 34, 4, 18, '2024-07-24', 1, 1, '161', '0', '0', 'M', '', '', '', '', 464),
(189, 'joseluis.tejeda@mess.com.mx', 'joseluis.tejeda', '499', 'José Luis Tejeda Brito', 'joseluis.tejeda@mess.com.mx', 38, 4, 18, '2024-08-01', 1, 1, '161', '0', '0', 'F', '13109401177', 'TEBL9403316MA', 'TEMBL940331HHGJRS06', '', 470),
(190, 'ariadna.galindo@mess.com.mx', 'ariadna.galindo', '500', 'Ariadna Adinai Galindo Gaytan', 'ariadna.galindo@mess.com.mx', 32, 4, 25, '2024-08-01', 1, 1, '42', '0', '0', 'F', '', '', '', '', 465),
(191, 'yessica.hernandez@mess.com.mx', 'yessica.hernandez', '501', 'Yessica Hernández Esparza ', 'yessica.hernandez@mess.com.mx', 6, 1, 40, '2024-01-01', 1, 1, '37', '0', '0', 'M', '', '', '', '', 468),
(192, 'edgar.alvarez@mess.com.mx', 'entradas.norte', '502', 'Edgar Emmanuel Alvarez Gómez ', 'edgar.alvarez@mess.com.mx', 6, 10, 0, '2024-09-04', 1, 1, '0', '0', '0', 'M', '2169901044', 'AAGE991217DX2', 'AAGE991217HSPLMD07', '', 112),
(193, 'jose.porras@mess.com.mx', 'jose.porras', '503', 'José María Porras Contreras', 'jose.porras@mess.com.mx', 38, 1, 1, '2024-09-09', 1, 1, '487', '0', '0', 'M', '5199831933', 'POCM98022LX4', 'POCM980227HVZRNR08', 'ARH+', 475),
(194, 'socorro.gutierrez@mess.com.mx', 'socorro.gutierrez', '504', 'María del Socorro Gutierrez Pérez ', 'socorro.gutierrez@mess.com.mx', 8, 10, 0, '2024-09-09', 1, 1, '0', '0', '0', 'M', '41058408026', 'GUPS840627QX2', 'GUPS840627MSPTRC08', '', 476),
(195, 'fernanda.rodriguez@mess.com.mx', 'fernanda.rodriguez', '505', 'Sonia Fernanda Rodríguez Calvillo', 'fernanda.rodriguez@mess.com.mx', 8, 1, 40, '2024-01-01', 1, 1, '37', '0', '0', 'M', '', '', '', '', 474),
(196, 'dafne@mess.com.mx', 'dafne', '506', 'Dafne Areli Sil Heredia ', 'dafne@mess.com.mx', 38, 2, 8, '2024-01-01', 1, 1, '0', '0', '0', 'M', '', '', '', '', 477),
(197, 'francisco.bocanegra@mess.com.mx', 'francisco.bocanegra', '507', 'Francisco Javier Velazquez Bocanegra ', 'francisco.bocanegra@mess.com.mx', 38, 8, 6, '2024-01-01', 1, 1, '11', '0', '0', 'M', '', '', '', '', 503),
(198, 'Xcalidad.aux@mess.com.mx', 'calidad.aux', '508', 'Daniel Aaron Olvera Guerrero', 'Xcalidad.aux@mess.com.mx', 36, 4, 4, '2024-10-21', 1, 1, '5', '0', '0', 'M', '35190029104', 'OEGD0006217G0', 'OEGD000621HQTLRNA5', 'ORH+', 481),
(200, 'ControlyMejoraMTS@mess.com.mx', 'PEFV810205CJ0', '516', 'Veronica Perez Fuentes', 'ControlyMejoraMTS@mess.com.mx', 8, 4, 29, '2025-01-20', 1, 1, '123', '0', 'PLANTA', 'F', '14068110072', 'PEFV810205CJ0', 'PEFV810205MQTRNR01', 'ORH+', 433),
(201, 'hugo.soria@mess.com.mx', 'Hugo.soria', '521', 'Hugo Ernesto Soria Mosqueda', 'hugo.soria@mess.com.mx', 66, 4, 41, '2025-02-04', 1, 3, '19', '0', 'PLANTA', 'M', '', '', '', '', 489),
(204, 'sandra.galindo@mess.com.mx', 'sandra.galindo', '8', 'Sandra Jannette Galindo', 'sandra.galindo@mess.com.mx', 32, 4, 25, '2024-08-01', 1, 1, '11', '0', '0', 'F', '', '', '', '', 405),
(205, 'odalis.hernandez@mess.com.mx', '2406sep3105.', '509', 'Hernández Guzmán Odalis Isabel ', 'odalis.hernandez@mess.com.mx', 38, 4, 29, '2024-12-16', 1, 1, '123', '0', 'PLANTA', 'F', 'S/R', 'S/R', 'S/R', '', 493),
(207, 'katia.azalea@mess.com.mx', 'katia.azalea', '519', 'Sanchez Porras Katia Azalea', 'katia.azalea@mess.com.mx', 8, 10, 40, '2025-01-27', 1, 1, '288', '12', 'PLANTA', 'F', '0', '0', '0', '', 376),
(206, 'miguel.resendiz@mess.com.mx', 'miguel.resendiz', '510', 'Resendiz Guapilla Miguel', 'miguel.resendiz@mess.com.mx', 38, 4, 27, '2025-01-06', 1, 1, '110', '15', 'PLANTA', 'M', 'S/R-0', 'S/R', 'S/R', 'S/R', 485),
(208, 'angel.olague@mess.com.mx', 'angel.olague', '512', 'José Angel Olague Herrera', 'angel.olague@mess.com.mx', 8, 4, 0, '2026-04-06', 0, 1, '0', '0', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 483),
(209, 'fernando.orozco@mess.com.mx', 'fernando.orozco', '513', 'Fernando Orozco Esparza', 'fernando.orozco@mess.com.mx', 8, 4, 36, '2025-01-07', 1, 1, '37', '1', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 484),
(210, 'roman.mayorga@mess.com.mx', 'roman.mayorga', '515', 'Luis Roman Mayorga Blanco', 'roman.mayorga@mess.com.mx', 8, 4, 0, '2025-01-16', 1, 1, '37', '2', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 486),
(237, 'alexis.fundora@mess.com.mx', 'alexis.fundora', '528', 'Alexis Fundora', 'alexis.fundora@mess.com.mx', 34, 4, 21, '2025-03-26', 1, 1, '14', '0', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 492),
(212, 'graciela.lopez@mess.com.mx', 'graciela.lopez', '517', 'Graciela de Fátima López Ibarra ', 'graciela.lopez@mess.com.mx', 8, 4, 0, '2025-01-22', 1, 1, '85', '4', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 487),
(213, 'entradas.norte@mess.com.mx', 'entradas.norte', '518', 'José Alejandro Hernández Hernández ', 'entradas.norte@mess.com.mx', 8, 4, 0, '2025-01-22', 1, 1, '11', '5', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 469),
(214, 'martin.bustamante@mess.com.mx', 'martin.bustamante', '520', 'Martín de Jesús Bustamante Pérez ', 'martin.bustamante@mess.com.mx', 8, 4, 0, '2025-01-28', 1, 1, '298', '6', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 488),
(215, 'mariana.candia@mess.com.mx', 'mariana.candia', '522', 'Mariana Candia García  ', 'mariana.candia@mess.com.mx', 8, 4, 0, '2025-08-15', 0, 1, '11', '7', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 490),
(216, 'cotizaciones2@mess.com.mx', 'cotizaciones2', '524', 'Mireya Karina Hernández Salinas', 'cotizaciones2@mess.com.mx', 8, 4, 40, '2025-02-17', 1, 1, '324', '8', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 494);
INSERT INTO `usuarios` (`id`, `usuario`, `password`, `noEmpleado`, `nombre`, `correo`, `puesto`, `region`, `departamento`, `fechaIngreso`, `estatus`, `rol`, `jefe`, `diasdisponibles`, `tipoContrato`, `sexo`, `nss`, `rfc`, `curp`, `tipoSangre`, `id_usuario`) VALUES
(217, 'andrea.dominguez@mess.com.mx', 'andrea.dominguez', '525', 'Martha Andrea Dominguez Alfaro', 'andrea.dominguez@mess.com.mx', 8, 4, 40, '2025-02-24', 1, 1, '37', '9', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 497),
(218, 'julian.martinez@mess.com.mx', 'julian.martinez', '527', 'Carlos Julián Martínez Ramírez', 'julian.martinez@mess.com.mx', 8, 4, 0, '2025-08-15', 0, 1, '0', '10', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 502),
(219, 'calidad.sfg@mess.com.mx', 'calidad.sfg', '558', 'Victor Badillo Estrada', 'calidad.sfg@mess.com.mx', 8, 4, 36, '2026-01-16', 1, 1, '45', '11', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 496),
(220, 'oswaldo.romero@mess.com.mx', 'oswaldo.romero', '531', 'Oswaldo Fabian Romero Estrella', 'oswaldo.romero@mess.com.mx', 8, 4, 0, '2025-04-16', 1, 1, '37', '12', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 505),
(221, 'gerardo.hernandez@mess.com.mx', 'gerardo.hernandez', '532', 'Gerardo Hernández Camargo ', 'gerardo.hernandez@mess.com.mx', 8, 4, 0, '2025-08-15', 1, 1, '123', '13', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 509),
(222, 'calidad.aux@mess.com.mx', 'calidad.aux', '533', 'Dulce Beatriz Ramirez Lopez', 'calidad.aux@mess.com.mx', 8, 4, 4, '2025-05-05', 1, 1, '5', '14', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 506),
(223, 'cotizaciones4@mess.com.mx', 'cotizaciones4', '534', 'Jenny Shamanta Arredondo Hernández', 'cotizaciones4@mess.com.mx', 8, 4, 40, '2025-08-15', 0, 1, '0', '15', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 507),
(224, 'patricio.espino@mess.com.mx', 'patricio.espino', '536', 'Patricio Javier Espino Becerra ', 'patricio.espino@mess.com.mx', 8, 4, 21, '2025-06-13', 1, 1, '14', '16', 'PLANTA', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 499),
(225, 'german.garza@mess.com.mx', 'german.garza', '537', 'German Antonio Garza Reyes', 'german.garza@mess.com.mx', 8, 4, 0, '2025-06-30', 1, 1, '37', '17', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 510),
(226, 'luis.ramos@mess.com.mx', 'luis.ramos', '538', 'Luis Fernando Ramos Gonzalez', 'luis.ramos@mess.com.mx', 8, 4, 0, '2025-07-01', 1, 1, '226', '18', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 516),
(227, 'jesus.arteaga@mess.com.mx', 'jesus.arteaga', '539', 'Jesús Armando Arteaga Rioyos', 'jesus.arteaga@mess.com.mx', 8, 4, 0, '2025-07-01', 1, 1, '226', '19', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 515),
(228, 'rafael.enciso@mess.com.mx', 'rafael.enciso', '540', 'Rafael Martin Enciso Cuevas', 'rafael.enciso@mess.com.mx', 8, 4, 0, '2025-08-15', 1, 1, '0', '20', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 514),
(229, 'admon.sfg@mess.com.mx', 'admon.sfg', '555', 'Saira Lizet Cárdelas Vázquez', 'admon.sfg@mess.com.mx', 8, 4, 36, '2025-12-11', 1, 1, '45', '0', 'TEMPORAL', 'F', 'S/R', 'S/R', 'S/R', 'S/R', 528),
(230, 'angelica.lopez@mess.com.mx', 'angelica.lopez', '542', 'Gloria Angelica Lopez Ramos', 'angelica.lopez@mess.com.mx', 8, 4, 0, '2025-08-15', 1, 1, '0', '22', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 513),
(231, 'jorge.romero@mess.com.mx', 'jorge.romero', '543', 'Jorge Luis Romero Gutiérrez', 'jorge.romero@mess.com.mx', 8, 4, 36, '2025-08-15', 1, 1, '45', '23', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 517),
(232, 'violeta.navarro@mess.com.mx', 'violeta.navarro', '544', 'Wendy Violeta Navarro San Vicente', 'violeta.navarro@mess.com.mx', 8, 4, 0, '2025-08-15', 1, 1, '0', '24', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 518),
(233, 'francisco.montero@mess.com.mx', 'francisco.montero', '545', 'Francisco Salvador Montero Olvera', 'francisco.montero@mess.com.mx', 8, 4, 0, '2025-08-15', 1, 1, '0', '25', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 519),
(234, 'almacen.aux@mess.com.mx', 'almacen.aux', '1004', 'Manuel Francisco Melo Torres', 'almacen.aux@mess.com.mx', 8, 4, 0, '2025-08-15', 1, 1, '0', '26', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 393),
(244, 'leticia.ledesma@mess.com.mx', 'leticia.ledesma', '554', 'Ledesma Velazquez Leticia', 'leticia.ledesma@mess.com.mx', 8, 4, 2, '2025-11-03', 1, 2, '71', '0', 'PLANTA', 'F', '', '', '', '', 527),
(239, 'pedro.rico@mess.com.mx', 'pedro.rico', '614', 'Rico Cardenas Pedro Octavio', 'pedro.rico@mess.com.mx', 38, 4, 25, '2025-01-16', 1, 1, '260', '0', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 501),
(240, 'jorge.sanchez@mess.com.mx', 'jorge.sanchez', '548', 'Jorge Arturo Sanchez Cruz', 'jorge.sanchez@mess.com.mx', 8, 4, 0, '2025-09-17', 1, 1, '298', '0', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', 'S/R', 523),
(241, 'eduardo.garcia@mess.com.mx', 'eduardo.garcia', '550', 'Juan Eduardo Garcia Arreola', 'eduardo.garcia@mess.com.mx', 8, 4, 2, '2025-10-01', 1, 1, '71', '0', 'TEMPORAL', 'M', 'S/R', 'S/R', 'S/R', '', 524),
(242, 'alan.gonzalez@mess.com.mx', 'alan.gonzalez', '398', 'Alan Gonzalez Nuñez', 'alan.gonzalez@mess.com.mx', 38, 4, 29, '2025-09-01', 1, 1, '123', '16', 'PLANTA', 'M', '', '', '', '', 520),
(243, 'esmeralda.chavira@mess.com.mx', 'esmeralda.chavira', '549', 'Nayeli Esmeralda Chavira Galván', 'esmeralda.chavira@mess.com.mx', 8, 10, 40, '2025-09-24', 1, 1, '288', '12', 'PLANTA', 'F', '0', '0', '0', '', 522),
(246, 'updates@mess.com.mx', 'practicasTI2026', '987654', 'Practicas ', 'updates@mess.com.mx', 42, 4, 38, '2026-01-19', 1, 1, '142', '0', 'PRACTICAS', 'F', '0', '0', '0', '0', 987654),
(247, 'jorge.botello@mess.com.mx', 'jorge.botello', '564', 'Jorge Luis Botello', 'jorge.botello@mess.com.mx', 38, 4, 18, '2026-02-19', 1, 1, '161', '0', '0', 'M', 'NA', 'NA', 'NA', '', 537),
(248, 'ricardo.aggi@mess.com.mx', 'ricardo.aggi', '565', 'Ricardo Aggi Vazquez', 'ricardo.aggi@mess.com.mx', 38, 4, 36, '2026-02-23', 1, 1, '45', '12', 'PLANTA', 'M', 'S/R', 'S/R', 'S/R', '', 511),
(249, 'lcexterno@mess.com.mx', 'lcexterno2026', '4444', 'Externo Lab Calibraciones', 'lcexterno@mess.com.mx', 38, 4, 18, '2026-02-02', 1, 1, '161', '0', '0', 'M', '', '', '', '', 4444),
(250, 'jesus.montoya@mess.com.mx', 'jesus.montoya', '566', 'Jesús Bernardo Montoya Ledezma', 'jesus.montoya@mess.com.mx', 38, 4, 29, '2026-03-02', 1, 1, '123', '16', 'PLANTA', 'M', '', '', '', 'ORH+', 540),
(251, 'omar.garcia@mess.com.mx', 'omar.garcia', '571', 'Omar Garcia Romero', 'omar.garcia@mess.com.mx', 43, 4, 29, '2026-03-24', 1, 1, '123', '0', 'PLANTA', 'M', 'NA', 'NA', 'NA', 'NA', 541),
(252, 'alexis.vazquez@mess.com.mx', 'alexis.vazquez', '362', 'Daniel Alexis Vazquez Castillo', 'alexis.vazquez@mess.com.mx', 38, 4, 29, '2026-04-09', 1, 1, '123', '0', 'TEMPORAL', 'M', '', '', '', '', 9999),
(253, 'cristhian.barrios@mess.com.mx', 'cristhian.barrios', '557', 'Cristhian José Barrios Partida', 'cristhian.barrios@mess.com.mx', 8, 10, 40, '2026-01-07', 1, 1, '288', '12', 'PLANTA', 'F', '0', '0', '0', '', 530),
(254, 'gabriela.huaracha@mess.com.mx', 'gabriela.huaracha', '572', 'Gabriela Huaracha Ramirez', 'gabriela.huaracha@mess.com.mx', 18, 10, 40, '2026-04-02', 1, 1, '288', '0', 'TEMPORAL', 'F', '0', '0', '0', '', 542);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=255;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
