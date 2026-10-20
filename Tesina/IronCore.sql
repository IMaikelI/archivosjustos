-- =====================================================================
--  MaxRep — esquema completo (instalación nueva)
--  Uso: importar en phpMyAdmin o `mysql -u root < IronCore.sql`
--  Para actualizar una base existente del esquema anterior, usar
--  Instalar/migrar.php en lugar de este archivo.
-- =====================================================================

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS ironcore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ironcore;

CREATE TABLE Musculos (
  id   INT PRIMARY KEY,
  Name VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE Users (
  id             INT PRIMARY KEY AUTO_INCREMENT,
  Name           VARCHAR(80)  NOT NULL,
  Email          VARCHAR(190) NOT NULL,
  Pass           VARCHAR(255) NOT NULL,
  Role           ENUM('usuario','profesor') NOT NULL DEFAULT 'usuario',
  Photo          VARCHAR(255) NULL,
  Peso           DECIMAL(5,1) NULL,
  Altura         SMALLINT     NULL,
  Objetivos      VARCHAR(500) NULL,
  Theme          ENUM('claro','oscuro') NOT NULL DEFAULT 'claro',
  CodigoProfesor CHAR(8)      NULL,
  ProfesorId     INT          NULL,
  master         BOOLEAN      NOT NULL DEFAULT FALSE,
  CreatedAt      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (Email),
  UNIQUE KEY uq_users_codigo (CodigoProfesor),
  CONSTRAINT fk_users_profesor FOREIGN KEY (ProfesorId) REFERENCES Users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE Ejercicios (
  id   INT PRIMARY KEY,
  Name VARCHAR(120) NOT NULL,
  Type VARCHAR(20)  NOT NULL,
  Mu   INT          NOT NULL,
  Info TEXT         NULL,
  Img  VARCHAR(255) NULL,
  Vid  VARCHAR(255) NULL,
  KEY ix_ejercicios_mu (Mu)
) ENGINE=InnoDB;

-- Rutinas. UserId = dueño (alumno o profesor). Version = control de concurrencia (CU-05, 5.3).
CREATE TABLE Works (
  id        INT PRIMARY KEY AUTO_INCREMENT,
  UserId    INT          NOT NULL,
  CreatedBy INT          NULL,
  Name      VARCHAR(80)  NOT NULL,
  Dia       TINYINT      NULL,
  Version   INT          NOT NULL DEFAULT 1,
  UpdatedBy INT          NULL,
  UpdatedAt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_works_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE WorkItems (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  WorkId      INT     NOT NULL,
  Pos         INT     NOT NULL,
  EjercicioId INT     NOT NULL,
  Sets        TINYINT NOT NULL DEFAULT 3,
  Reps        TINYINT NOT NULL DEFAULT 10,
  CONSTRAINT fk_items_work FOREIGN KEY (WorkId) REFERENCES Works(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_ej   FOREIGN KEY (EjercicioId) REFERENCES Ejercicios(id)
) ENGINE=InnoDB;

-- Rutinas compartidas con otros usuarios mediante código (solo lectura)
CREATE TABLE WorkShares (
  WorkId INT NOT NULL,
  UserId INT NOT NULL,
  PRIMARY KEY (WorkId, UserId),
  CONSTRAINT fk_shares_work FOREIGN KEY (WorkId) REFERENCES Works(id) ON DELETE CASCADE,
  CONSTRAINT fk_shares_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Códigos temporales para compartir una rutina
CREATE TABLE Tokens (
  n         VARCHAR(10) PRIMARY KEY,
  date      DATETIME    NOT NULL,
  ruti      INT         NOT NULL,
  CreatedBy INT         NULL
) ENGINE=InnoDB;

-- Marcas personales (peso levantado por ejercicio y fecha)
CREATE TABLE Records (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  UserId      INT          NOT NULL,
  EjercicioId INT          NOT NULL,
  Peso        DECIMAL(6,2) NOT NULL,
  Fecha       DATE         NOT NULL,
  KEY ix_records_user (UserId, EjercicioId, Fecha),
  CONSTRAINT fk_records_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE,
  CONSTRAINT fk_records_ej   FOREIGN KEY (EjercicioId) REFERENCES Ejercicios(id)
) ENGINE=InnoDB;

CREATE TABLE Subscriptions (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  UserId      INT          NOT NULL,
  Plan        VARCHAR(40)  NOT NULL,
  Status      ENUM('activa','cancelada') NOT NULL DEFAULT 'activa',
  StartedAt   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CancelledAt DATETIME     NULL,
  CONSTRAINT fk_subs_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Payments (
  id             INT PRIMARY KEY AUTO_INCREMENT,
  UserId         INT           NOT NULL,
  SubscriptionId INT           NULL,
  Amount         DECIMAL(10,2) NOT NULL,
  Currency       CHAR(3)       NOT NULL,
  Status         ENUM('aprobado','rechazado') NOT NULL,
  Mode           VARCHAR(10)   NOT NULL,
  CardBrand      VARCHAR(20)   NULL,
  CardLast4      CHAR(4)       NULL,
  Reference      VARCHAR(40)   NOT NULL,
  CreatedAt      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE SupportMessages (
  id        INT PRIMARY KEY AUTO_INCREMENT,
  UserId    INT          NOT NULL,
  Asunto    VARCHAR(120) NOT NULL,
  Mensaje   TEXT         NOT NULL,
  CreatedAt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_support_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Datos
-- ---------------------------------------------------------------------

INSERT INTO Musculos (id, Name) VALUES
(0,'Pecho'),(1,'Espalda'),(2,'Hombros'),(3,'Bíceps'),(4,'Tríceps'),
(5,'Cuádriceps'),(6,'Isquiotibiales'),(7,'Gemelos');

INSERT INTO Ejercicios (id, Name, Type, Mu) VALUES
-- PECHO
(101, 'Press de banca plano con barra', 'Libre', 0),
(102, 'Press de banca inclinado con barra', 'Libre', 0),
(103, 'Press de banca declinado con barra', 'Libre', 0),
(104, 'Press de banca plano con mancuernas', 'Libre', 0),
(105, 'Press de banca inclinado con mancuernas', 'Libre', 0),
(106, 'Press de banca declinado con mancuernas', 'Libre', 0),
(107, 'Aperturas con mancuernas plano', 'Libre', 0),
(108, 'Aperturas con mancuernas inclinado', 'Libre', 0),
(109, 'Cruce de poleas (cable crossover)', 'Cable', 0),
(110, 'Press en maquina Smith plano', 'Libre', 0),
(111, 'Press en maquina Smith inclinado', 'Libre', 0),
(112, 'Fondos en paralelas (dips) para pecho', 'Libre', 0),
(113, 'Flexiones de brazos (push-ups)', 'Libre', 0),
(114, 'Flexiones con manos cerradas', 'Libre', 0),
(115, 'Flexiones con pies elevados', 'Libre', 0),
(116, 'Pullover con mancuerna', 'Libre', 0),
(117, 'Press en maquina Hammer Strength plano', 'Libre', 0),
(118, 'Press en maquina Hammer Strength inclinado', 'Libre', 0),
(119, 'Peck deck (contractora de pecho)', 'Libre', 0),
(120, 'Press con banda elastica', 'Libre', 0),

-- ESPALDA
(201, 'Dominadas pronas (pull-ups)', 'Libre', 1),
(202, 'Dominadas supinas (chin-ups)', 'Libre', 1),
(203, 'Remo con barra', 'Libre', 1),
(204, 'Remo con mancuerna a un brazo', 'Libre', 1),
(205, 'Remo en maquina sentado (cable row)', 'Cable', 1),
(206, 'Jalon al pecho (lat pulldown)', 'Cable', 1),
(207, 'Jalon tras nuca', 'Cable', 1),
(208, 'Peso muerto convencional', 'Libre', 1),
(209, 'Peso muerto rumano', 'Libre', 1),
(210, 'Remo en maquina Hammer Strength', 'Libre', 1),
(211, 'Remo en punta (T-bar row)', 'Libre', 1),
(212, 'Pull-over en polea alta', 'Cable', 1),
(213, 'Remo invertido (Australian pull-up)', 'Libre', 1),
(214, 'Jalon con agarre cerrado', 'Cable', 1),
(215, 'Remo con barra agarre supino', 'Libre', 1),
(216, 'Face pull', 'Cable', 1),
(217, 'Hiperextensiones lumbares', 'Libre', 1),
(218, 'Remo Pendlay', 'Libre', 1),
(219, 'Jalon unilateral en polea', 'Cable', 1),
(220, 'Encogimientos con barra (shrugs)', 'Libre', 1),

-- BICEPS
(401, 'Curl con barra recta', 'Libre', 3),
(402, 'Curl con barra Z', 'Libre', 3),
(403, 'Curl con mancuernas alterno', 'Libre', 3),
(404, 'Curl martillo', 'Libre', 3),
(405, 'Curl concentrado', 'Libre', 3),
(406, 'Curl en banco Scott (predicador)', 'Libre', 3),
(407, 'Curl en polea baja', 'Cable', 3),
(408, 'Curl en polea alta (doble polea)', 'Cable', 3),
(409, 'Curl 21s', 'Libre', 3),
(410, 'Curl con banda elastica', 'Libre', 3),
(411, 'Curl spider', 'Libre', 3),
(412, 'Curl inclinado con mancuernas', 'Libre', 3),
(413, 'Curl con cuerda en polea', 'Cable', 3),
(414, 'Curl con barra EZ agarre cerrado', 'Libre', 3),
(415, 'Curl con barra EZ agarre ancho', 'Libre', 3),
(416, 'Curl cruzado (crossbody curl)', 'Libre', 3),
(417, 'Curl isometrico', 'Libre', 3),
(418, 'Curl con mancuernas en supinacion', 'Libre', 3),
(419, 'Curl unilateral en polea baja', 'Cable', 3),
(420, 'Curl drag (drag curl)', 'Libre', 3),

-- TRICEPS
(501, 'Press frances con barra', 'Libre', 4),
(502, 'Press frances con mancuerna', 'Libre', 4),
(503, 'Extension de triceps en polea alta (pushdown)', 'Cable', 4),
(504, 'Extension de triceps con cuerda', 'Cable', 4),
(505, 'Extension de triceps unilateral en polea', 'Cable', 4),
(506, 'Patada de triceps (kickback)', 'Libre', 4),
(507, 'Fondos en banco (bench dips)', 'Libre', 4),
(508, 'Fondos en paralelas para triceps', 'Libre', 4),
(509, 'Press cerrado con barra (close grip bench press)', 'Libre', 4),
(510, 'Extension sobre la cabeza con mancuerna', 'Libre', 4),
(511, 'Extension sobre la cabeza con polea', 'Cable', 4),
(512, 'Jalon de triceps con barra recta', 'Cable', 4),
(513, 'Jalon de triceps con barra V', 'Cable', 4),
(514, 'Extension acostado con mancuernas (skull crusher)', 'Libre', 4),
(515, 'Press JM', 'Libre', 4),
(516, 'Extension unilateral con mancuerna sobre la cabeza', 'Libre', 4),
(517, 'Flexiones diamante (diamond push-ups)', 'Libre', 4),
(518, 'Extension de triceps en maquina', 'Libre', 4),
(519, 'Kickback en polea baja', 'Cable', 4),
(520, 'Press de banca agarre cerrado en Smith', 'Libre', 4),

-- CUADRICEPS
(601, 'Sentadilla libre con barra', 'Libre', 5),
(602, 'Sentadilla frontal', 'Libre', 5),
(603, 'Prensa de piernas (leg press)', 'Libre', 5),
(604, 'Extension de cuadriceps en maquina', 'Libre', 5),
(605, 'Sentadilla en maquina Smith', 'Libre', 5),
(606, 'Sentadilla bulgara', 'Libre', 5),
(607, 'Zancadas (lunges) con mancuernas', 'Libre', 5),
(608, 'Sentadilla goblet', 'Libre', 5),
(609, 'Sentadilla hack (hack squat)', 'Libre', 5),
(610, 'Zancadas caminando', 'Libre', 5),
(611, 'Sentadilla sumo', 'Libre', 5),
(612, 'Step up con mancuernas', 'Libre', 5),
(613, 'Sentadilla pistol asistida', 'Libre', 5),
(614, 'Sentadilla en multipower talones elevados', 'Libre', 5),
(615, 'Extension unilateral de cuadriceps', 'Libre', 5),

-- ISQUIOS
(701, 'Peso muerto rumano con barra', 'Libre', 6),
(702, 'Curl femoral acostado en maquina', 'Libre', 6),
(703, 'Curl femoral sentado en maquina', 'Libre', 6),
(704, 'Peso muerto con piernas rigidas', 'Libre', 6),
(705, 'Buenos dias (good mornings)', 'Libre', 6),
(706, 'Peso muerto rumano con mancuernas', 'Libre', 6),
(707, 'Curl femoral de pie unilateral', 'Libre', 6),
(708, 'Hip thrust con barra', 'Libre', 6),
(709, 'Puente de gluteos con isquios', 'Libre', 6),
(710, 'Peso muerto sumo', 'Libre', 6),
(711, 'Curl nordico', 'Libre', 6),
(712, 'Swing con kettlebell', 'Libre', 6),
(713, 'Peso muerto rumano a una pierna', 'Libre', 6),
(714, 'Curl femoral con banda elastica', 'Libre', 6),
(715, 'Elevacion de cadera en maquina', 'Libre', 6),

-- GEMELOS
(801, 'Elevacion de talones de pie', 'Libre', 7),
(802, 'Elevacion de talones sentado', 'Libre', 7),
(803, 'Elevacion de talones en prensa de piernas', 'Libre', 7),
(804, 'Elevacion de talones en maquina Smith', 'Libre', 7),
(805, 'Elevacion de talones a una pierna', 'Libre', 7),
(806, 'Elevacion de talones en step', 'Libre', 7),
(807, 'Elevacion de talones con mancuernas', 'Libre', 7),
(808, 'Elevacion de talones en burro (donkey calf raise)', 'Libre', 7),
(809, 'Saltos de cuerda para gemelos', 'Libre', 7),
(810, 'Elevacion de talones isometrica', 'Libre', 7),
(811, 'Elevacion de talones en hack squat', 'Libre', 7),
(812, 'Elevacion de talones con barra', 'Libre', 7),
(813, 'Caminata en punta de pies', 'Libre', 7),
(814, 'Elevacion de talones explosiva (jump calf raise)', 'Libre', 7),
(815, 'Elevacion de talones unilateral en maquina', 'Libre', 7);

-- Usuarios de demostración (contraseña de ambos: Demo1234). Cambiala o borralos en producción.
INSERT INTO Users (id, Name, Email, Pass, Role, CodigoProfesor) VALUES
(1, 'Ejemplo',    'Ejemplo@gmail.com',  '$2y$10$MDWIkqW6JSRiHpNEn8AEn.5dRGO6oEv860Oz6UOabsJZiJeacJFRC', 'usuario',  NULL),
(2, 'Profe Demo', 'profe@maxrep.local', '$2y$10$MDWIkqW6JSRiHpNEn8AEn.5dRGO6oEv860Oz6UOabsJZiJeacJFRC', 'profesor', 'PROF2026');
UPDATE Users SET ProfesorId = 2 WHERE id = 1;

INSERT INTO Works (id, UserId, CreatedBy, Name, Dia) VALUES (1, 1, 1, 'Ejemplo', 1);
INSERT INTO WorkItems (WorkId, Pos, EjercicioId, Sets, Reps) VALUES
(1, 0, 101, 3, 4), (1, 1, 201, 3, 4), (1, 2, 102, 2, 4);

INSERT INTO Records (UserId, EjercicioId, Peso, Fecha) VALUES
(1, 101, 60, CURDATE() - INTERVAL 14 DAY),
(1, 101, 65, CURDATE() - INTERVAL 7 DAY);

-- Limpieza de códigos de rutina vencidos (requiere event_scheduler=ON; la app también valida la expiración)
CREATE EVENT IF NOT EXISTS borrar_tokens
ON SCHEDULE EVERY 30 MINUTE
STARTS CURRENT_TIMESTAMP
DO
  DELETE FROM Tokens WHERE date < NOW() - INTERVAL 30 MINUTE;
