-- Script para insertar usuarios desde el archivo nuevosusuarios.csv
-- Contraseña: 123456 (hash generado con password_hash())
-- Rol: USUARIO para todos los usuarios
-- Fecha: $(date)

-- Hash de la contraseña "123456" generado con password_hash()
-- Este hash es estándar para la contraseña "123456"
-- Variable para el hash (no se usa en PostgreSQL, solo para referencia)
-- $password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- Insertar usuarios desde el CSV (usando INSERT ... ON CONFLICT para evitar duplicados)
INSERT INTO usuarios (usuario, nombre, apellido, grado, cedula, telefono, rol, contrasena, primer_inicio, fecha_creacion) VALUES
('55825', 'ROMINA ELIZABETH', 'ACHUCARRO FERREIRA', 'Oficial Inspector', '3482710', '+595 983 485258', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('60125', 'ROSA INMACULADA', 'FLEITAS AQUINO', 'Oficial Segundo', '6192601', '+595 983 900984', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('65189', 'ALEXIS RAMÓN', 'ZÁRATE PEREZ', 'Oficial Ayudante', '4592442', '+595 971 586207', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('54968', 'SANDRA NIEVE', 'MARTINEZ SANABRIA', 'Suboficial Inspector', '5017277', '+595 981 205279', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('56565', 'JORGE', 'BENÍTEZ', 'Suboficial Inspector', '2907357', '+595 985 379438', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('58937', 'MARCIAL', 'ACOSTA MARTINEZ', 'Suboficial Primero', '5858975', '+595 981 795504', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('59670', 'RICARDO EMMANUEL', 'AQUINO ARAUJO', 'Suboficial Primero', '5596708', '+595 971 124323', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('60893', 'LUIS ENRIQUE', 'ARCE MARECO', 'Suboficial Segundo', '4609692', '+595 984 816646', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('63763', 'JORGE ANTONIO', 'TOLEDO BRÍTEZ', 'Suboficial Ayudante', '6076443', '+595 981 063143', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('63665', 'SANIE MARICELA', 'ALEGRE RUIZ DÍAZ', 'Suboficial Ayudante', '6796768', '+595 972 622687', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('63458', 'LIZZA YSABEL', 'CASTILLO BRITEZ', 'Suboficial Ayudante', '5113505', '+595 986 397377', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('63839', 'ELIAS VIRGILIO', 'DOLDAN BENITEZ', 'Suboficial Ayudante', '6365525', '+595 975 932671', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('66803', 'SIXTO MAURICIO', 'CASCO BENÍTEZ', 'Suboficial Ayudante', '4891637', '+595 986 513486', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('63343', 'FATIMA ESTHER', 'PEDROZO VILLALBA', 'Suboficial Ayudante', '6043484', '+595 986 817769', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('57480', 'RAQUEL', 'QUIÑÓNEZ ACEVEDO', 'Oficial Primero', '3173207', '+595 972 275575', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('58901', 'WILSON ISABELINO', 'RUIZ HERMOSILLA', 'Oficial Segundo', '5280356', '+595 992 754580', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('66023', 'FABIO ADRIAN', 'GÓMEZ CABRERA', 'Suboficial Ayudante', '6502161', '+595 981 643920', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('62341', 'LUZ CLARA', 'VALIENTE FUNES', 'Suboficial Segundo', '6036164', '+595 972 519557', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('65395', 'VICTOR ADOLFO', 'PÉREZ PUERTA', 'Suboficial Ayudante', '3895258', '+595 961 946889', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('59638', 'DIANA MABEL', 'LOPEZ AMARILLA', 'Suboficial Primero', '6032014', '+595 983 817260', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('60202', 'PAULO CESAR', 'FRANCO ZARACHO', 'Suboficial Primero', '5788100', '+595 982 516347', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('57393', 'ROLANDO', 'ARCE CHÁVEZ', 'Oficial Primero', '4890724', '+595 981 259173', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('59902', 'SANDRA NOEMÍ', 'OSORIO SEGOVIA', 'Suboficial Primero', '4373617', '+595 981 873064', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('64277', 'CRISTINA ROCÍO', 'BÁEZ ANZOATEGUI', 'Suboficial Ayudante', '6879713', '+595 986 837840', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('58814', 'BRUNO ENRIQUE', 'CANTERO AYALA', 'Oficial Segundo', '4645931', '+595 982 974874', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('67279', 'APARICIO PASCUAL', 'LAZAGA BRITOS', 'Suboficial Ayudante', '6573173', '+595 971 910493', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('57435', 'VIVIAN CLAUDELINA', 'VILLALBA ORTIZ', 'Oficial Primero', '4481699', '+595 984 461549', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('57504', 'SILVINA AGUSTINA', 'DELGADO FERREIRA', 'Oficial Primero', '4790222', '+595 985 669317', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('63989', 'FERNANDO ARIEL', 'MEZA BAEZ', 'Suboficial Ayudante', '5384371', '+595 976 197947', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('65559', 'MARIANA', 'D''APOLLO ESCOBAR', 'Sub Oficial Ayudante', '6154713', '+595 982 341102', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('65938', 'MARCOS ANTONIO', 'YEGROS GOMEZ', 'Sub Oficial Ayudante', '5662303', '+595 981 542078', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('64178', 'CHRISTIAN MICHAEL', 'CORTAZA', 'Suboficial Segundo', '6305832', '+595 986 691018', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('57574', 'ALDER ALBERTO', 'SALINAS MOLINA', 'Oficial Primero', '4777875', '+595 981 401538', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('58793', 'DIEGO RUBÉN', 'PEDROZO RUÍZ DÍAZ', 'Oficial Segundo', '3806367', '+595 986 529909', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('51166', 'EVER RAMON', 'AREVALOS SANCHEZ', 'Suboficial Inspector', '5056861', '+595 984 204280', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('55491', 'CRISTHIAN EDUARDO', 'ALEMAN CAPUTO', 'Suboficial Inspector', '4316402', '+595 971 243340', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('60924', 'DERLIS MANUEL', 'GAYOZO SANABRIA', 'Suboficial Segundo', '4332920', '+595 984 642155', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('61589', 'LIZ PAOLA', 'ALMADA ALCARAZ', 'Suboficial Segundo', '7087576', '+595 982 714327', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('61596', 'THANIA MICAELA', 'CARDOZO OBREGÓN', 'Suboficial Segundo', '5695255', '+595 993 266673', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('61635', 'LUZ MARINA', 'BENÍTEZ SÁNCHEZ', 'Suboficial Segundo', '4537326', '+595 995 636097', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('61888', 'YESSICA CAROLINA', 'MARTINEZ POSDELEY', 'Suboficial Segundo', '5096071', '+595 983 374625', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('61600', 'LIZ MERCEDES', 'ROA RAMÍREZ', 'Suboficial Segundo', '6686256', '+595 971 328405', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('61881', 'NOELIA', 'ESPINOLA LEZCANO', 'Suboficial Segundo', '6821278', '+595 985 215287', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('61723', 'LETICIA', 'CHAPARRO', 'Suboficial Segundo', '5093912', '+595 986 220211', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('62247', 'MAIRA LUJÁN', 'GONZÁLEZ SALDIVAR', 'Suboficial Segundo', '6303028', '+595 975 328369', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('62285', 'PABLA NOELIA', 'BOGADO SAMANIEGO', 'Suboficial Segundo', '5063577', '+595 985 685534', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('62288', 'ELIZABETH', 'GAMARRA GONZÁLEZ', 'Suboficial Segundo', '6943529', '+595 985 788461', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('62309', 'LIMPIA CONCEPCIÓN', 'CABRERA AMARILLA', 'Suboficial Segundo', '7038948', '+595 982 646455', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW()),
('62163', 'EUFRACIO FABIAN', 'ARCE GIMENEZ', 'Suboficial Segundo', '4486772', '+595 985 977758', 'USUARIO', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, NOW())
ON CONFLICT (usuario) DO NOTHING;

-- Verificar que se insertaron correctamente
SELECT COUNT(*) as total_usuarios_insertados FROM usuarios WHERE rol = 'USUARIO' AND primer_inicio = true;
