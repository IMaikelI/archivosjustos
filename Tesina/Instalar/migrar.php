<?php
/**
 * Migra una base "ironcore" del esquema anterior al esquema de MaxRep.
 *
 *   php Instalar/migrar.php            (recomendado, desde la carpeta del proyecto)
 *   http://localhost/Tesina/Instalar/migrar.php?confirmar=1   (solo desde la misma PC)
 *
 * Es idempotente: se puede ejecutar más de una vez. No borra columnas antiguas
 * (Users.Works, Users.records, Works.Ejercicios/Sets/Reps quedan como respaldo).
 * Hacé una copia de la base antes de ejecutarlo (phpMyAdmin > Exportar).
 */

require __DIR__ . '/../Conection.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!$local) {
        http_response_code(403);
        exit('La migración solo puede ejecutarse desde la misma computadora.');
    }
    header('Content-Type: text/plain; charset=utf-8');
    if (($_GET['confirmar'] ?? '') !== '1') {
        exit("Esto modificará la base '" . cfg('db_name') . "'.\nHacé un respaldo y abrí esta URL con ?confirmar=1 para continuar.\n");
    }
}

$log = static function (string $m) {
    echo $m, PHP_EOL;
};
$db = Conect();
$dbName = cfg('db_name');

$hasTable = static fn(string $t): bool => (bool) row(
    'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
    [$dbName, $t]
);
$hasCol = static fn(string $t, string $c): bool => (bool) row(
    'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    [$dbName, $t, $c]
);
$hasIndex = static fn(string $t, string $i): bool => (bool) row(
    'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
    [$dbName, $t, $i]
);
$hasFk = static fn(string $t, string $fk): bool => (bool) row(
    'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
    [$dbName, $t, $fk]
);
$addCol = static function (string $t, string $c, string $ddl) use ($hasCol, $db, $log) {
    if (!$hasCol($t, $c)) {
        $db->query("ALTER TABLE `$t` ADD COLUMN $ddl");
        $log("  + $t.$c");
    }
};

foreach (['Users', 'Works', 'Ejercicios'] as $t) {
    if (!$hasTable($t)) {
        exit("Falta la tabla $t. Para una instalación nueva importá IronCore.sql.\n");
    }
}

$log('1. Músculos y ejercicios');
$db->query('CREATE TABLE IF NOT EXISTS Musculos (id INT PRIMARY KEY, Name VARCHAR(40) NOT NULL) ENGINE=InnoDB');
$db->query("INSERT IGNORE INTO Musculos (id, Name) VALUES (0,'Pecho'),(1,'Espalda'),(2,'Hombros'),(3,'Bíceps'),
            (4,'Tríceps'),(5,'Cuádriceps'),(6,'Isquiotibiales'),(7,'Gemelos')");
$addCol('Ejercicios', 'Info', 'Info TEXT NULL');
$addCol('Ejercicios', 'Img', 'Img VARCHAR(255) NULL');
$addCol('Ejercicios', 'Vid', 'Vid VARCHAR(255) NULL');

$log('2. Usuarios');
$addCol('Users', 'Role', "Role ENUM('usuario','profesor') NOT NULL DEFAULT 'usuario'");
$addCol('Users', 'Peso', 'Peso DECIMAL(5,1) NULL');
$addCol('Users', 'Altura', 'Altura SMALLINT NULL');
$addCol('Users', 'Objetivos', 'Objetivos VARCHAR(500) NULL');
$addCol('Users', 'Theme', "Theme ENUM('claro','oscuro') NOT NULL DEFAULT 'claro'");
$addCol('Users', 'CodigoProfesor', 'CodigoProfesor CHAR(8) NULL');
$addCol('Users', 'ProfesorId', 'ProfesorId INT NULL');
$addCol('Users', 'CreatedAt', 'CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
$db->query('ALTER TABLE Users MODIFY Name VARCHAR(80) NOT NULL, MODIFY Email VARCHAR(190) NOT NULL, MODIFY Pass VARCHAR(255) NOT NULL, MODIFY Photo VARCHAR(255) NULL');

$hashed = 0;
foreach (rows('SELECT id, Pass FROM Users') as $u) {
    $p = (string) $u['Pass'];
    if ($p !== '' && password_get_info($p)['algo'] === null) {
        run('UPDATE Users SET Pass = ? WHERE id = ?', [password_hash($p, PASSWORD_DEFAULT), (int) $u['id']]);
        $hashed++;
    }
}
$log("  contraseñas en texto plano convertidas a hash: $hashed");

$dups = rows('SELECT LOWER(Email) e, COUNT(*) c FROM Users GROUP BY LOWER(Email) HAVING c > 1');
if ($dups) {
    $log('  ATENCIÓN: hay emails repetidos, no se creó el índice único: ' . implode(', ', array_column($dups, 'e')));
} elseif (!$hasIndex('Users', 'uq_users_email')) {
    $db->query('ALTER TABLE Users ADD UNIQUE KEY uq_users_email (Email)');
    $log('  + índice único de email');
}
if (!$hasIndex('Users', 'uq_users_codigo')) {
    $db->query('ALTER TABLE Users ADD UNIQUE KEY uq_users_codigo (CodigoProfesor)');
}
if (!$hasFk('Users', 'fk_users_profesor')) {
    $db->query('ALTER TABLE Users ADD CONSTRAINT fk_users_profesor FOREIGN KEY (ProfesorId) REFERENCES Users(id) ON DELETE SET NULL');
}

$log('3. Rutinas');
$addCol('Works', 'CreatedBy', 'CreatedBy INT NULL');
$addCol('Works', 'Dia', 'Dia TINYINT NULL');
$addCol('Works', 'Version', 'Version INT NOT NULL DEFAULT 1');
$addCol('Works', 'UpdatedBy', 'UpdatedBy INT NULL');
$addCol('Works', 'UpdatedAt', 'UpdatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
$db->query('ALTER TABLE Works MODIFY Name VARCHAR(80) NOT NULL');
foreach (['Ejercicios', 'Sets', 'Reps'] as $legacy) {
    if ($hasCol('Works', $legacy)) {
        $db->query("ALTER TABLE Works MODIFY `$legacy` TEXT NULL");
    }
}
$db->query('CREATE TABLE IF NOT EXISTS WorkItems (
  id INT PRIMARY KEY AUTO_INCREMENT, WorkId INT NOT NULL, Pos INT NOT NULL, EjercicioId INT NOT NULL,
  Sets TINYINT NOT NULL DEFAULT 3, Reps TINYINT NOT NULL DEFAULT 10,
  CONSTRAINT fk_items_work FOREIGN KEY (WorkId) REFERENCES Works(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_ej FOREIGN KEY (EjercicioId) REFERENCES Ejercicios(id)) ENGINE=InnoDB');
$db->query('CREATE TABLE IF NOT EXISTS WorkShares (
  WorkId INT NOT NULL, UserId INT NOT NULL, PRIMARY KEY (WorkId, UserId),
  CONSTRAINT fk_shares_work FOREIGN KEY (WorkId) REFERENCES Works(id) ON DELETE CASCADE,
  CONSTRAINT fk_shares_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE) ENGINE=InnoDB');

$validEj = array_flip(array_map('intval', array_column(rows('SELECT id FROM Ejercicios'), 'id')));
if ($hasCol('Works', 'Ejercicios')) {
    $moved = 0;
    $skipped = 0;
    foreach (rows('SELECT id, Ejercicios, Sets, Reps FROM Works') as $w) {
        $wid = (int) $w['id'];
        if (row('SELECT 1 FROM WorkItems WHERE WorkId = ?', [$wid]) || trim((string) $w['Ejercicios']) === '') {
            continue;
        }
        $ejs = array_map('trim', explode(',', (string) $w['Ejercicios']));
        $sets = array_map('trim', explode(',', (string) $w['Sets']));
        $reps = array_map('trim', explode(',', (string) $w['Reps']));
        $pos = 0;
        foreach ($ejs as $i => $ej) {
            $ej = (int) $ej;
            if (!isset($validEj[$ej])) {
                $skipped++;
                continue;
            }
            $s = max(1, min(50, (int) ($sets[$i] ?? 3)));
            $r = max(1, min(100, (int) ($reps[$i] ?? 10)));
            run('INSERT INTO WorkItems (WorkId, Pos, EjercicioId, Sets, Reps) VALUES (?,?,?,?,?)', [$wid, $pos++, $ej, $s, $r]);
            $moved++;
        }
    }
    $log("  ejercicios de rutinas migrados: $moved (omitidos por no existir: $skipped)");
}
run('UPDATE Works SET CreatedBy = UserId WHERE CreatedBy IS NULL');
$orph = row('SELECT COUNT(*) c FROM Works w LEFT JOIN Users u ON u.id = w.UserId WHERE u.id IS NULL')['c'];
if ($orph > 0) {
    $log("  ATENCIÓN: $orph rutinas tienen un dueño inexistente; no se agregó la clave foránea fk_works_user.");
} elseif (!$hasFk('Works', 'fk_works_user')) {
    $db->query('ALTER TABLE Works ADD CONSTRAINT fk_works_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE');
}

$log('4. Códigos para compartir');
if (!$hasTable('Tokens')) {
    $db->query('CREATE TABLE Tokens (n VARCHAR(10) PRIMARY KEY, date DATETIME NOT NULL, ruti INT NOT NULL, CreatedBy INT NULL) ENGINE=InnoDB');
} else {
    $db->query('DELETE FROM Tokens'); // eran temporales (30 minutos)
    $db->query('ALTER TABLE Tokens MODIFY n VARCHAR(10) NOT NULL, MODIFY date DATETIME NOT NULL, MODIFY ruti INT NOT NULL');
    $addCol('Tokens', 'CreatedBy', 'CreatedBy INT NULL');
}

$log('5. Marcas personales');
$db->query('CREATE TABLE IF NOT EXISTS Records (
  id INT PRIMARY KEY AUTO_INCREMENT, UserId INT NOT NULL, EjercicioId INT NOT NULL,
  Peso DECIMAL(6,2) NOT NULL, Fecha DATE NOT NULL, KEY ix_records_user (UserId, EjercicioId, Fecha),
  CONSTRAINT fk_records_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE,
  CONSTRAINT fk_records_ej FOREIGN KEY (EjercicioId) REFERENCES Ejercicios(id)) ENGINE=InnoDB');
if ($hasCol('Users', 'records')) {
    $moved = 0;
    $skipped = 0;
    foreach (rows('SELECT id, records FROM Users') as $u) {
        $uid = (int) $u['id'];
        $raw = trim((string) $u['records']);
        if ($raw === '' || $raw === '--' || row('SELECT 1 FROM Records WHERE UserId = ?', [$uid])) {
            continue;
        }
        foreach (explode(';', $raw) as $block) {
            $parts = explode(',', $block, 2);
            $ej = (int) trim($parts[0]);
            if (!isset($parts[1]) || !isset($validEj[$ej])) {
                $skipped++;
                continue;
            }
            foreach (explode('.', $parts[1]) as $entry) {
                $pf = array_map('trim', explode('-', $entry, 2));
                if (count($pf) < 2 || !is_numeric($pf[0])) {
                    continue;
                }
                $f = array_map('intval', explode('/', $pf[1]));
                if (count($f) === 3) {            // Y/m/d (formato de records.php)
                    [$y, $m, $d] = $f;
                } elseif (count($f) === 2) {      // d/m sin año: se asume el año actual
                    [$d, $m] = $f;
                    $y = (int) date('Y');
                    if (mktime(0, 0, 0, $m, $d, $y) > time()) {
                        $y--;
                    }
                } else {
                    continue;
                }
                if (!checkdate($m, $d, $y)) {
                    continue;
                }
                run('INSERT INTO Records (UserId, EjercicioId, Peso, Fecha) VALUES (?,?,?,?)',
                    [$uid, $ej, (float) $pf[0], sprintf('%04d-%02d-%02d', $y, $m, $d)]);
                $moved++;
            }
        }
    }
    $log("  marcas migradas: $moved (bloques omitidos por ejercicio inexistente: $skipped)");
}

$log('6. Suscripciones, pagos y soporte');
$db->query("CREATE TABLE IF NOT EXISTS Subscriptions (
  id INT PRIMARY KEY AUTO_INCREMENT, UserId INT NOT NULL, Plan VARCHAR(40) NOT NULL,
  Status ENUM('activa','cancelada') NOT NULL DEFAULT 'activa', StartedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CancelledAt DATETIME NULL, CONSTRAINT fk_subs_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE) ENGINE=InnoDB");
$db->query("CREATE TABLE IF NOT EXISTS Payments (
  id INT PRIMARY KEY AUTO_INCREMENT, UserId INT NOT NULL, SubscriptionId INT NULL, Amount DECIMAL(10,2) NOT NULL,
  Currency CHAR(3) NOT NULL, Status ENUM('aprobado','rechazado') NOT NULL, Mode VARCHAR(10) NOT NULL,
  CardBrand VARCHAR(20) NULL, CardLast4 CHAR(4) NULL, Reference VARCHAR(40) NOT NULL,
  CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE) ENGINE=InnoDB");
$db->query('CREATE TABLE IF NOT EXISTS SupportMessages (
  id INT PRIMARY KEY AUTO_INCREMENT, UserId INT NOT NULL, Asunto VARCHAR(120) NOT NULL, Mensaje TEXT NOT NULL,
  CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_support_user FOREIGN KEY (UserId) REFERENCES Users(id) ON DELETE CASCADE) ENGINE=InnoDB');

$log('7. Evento de limpieza de códigos');
try {
    $db->query('DROP EVENT IF EXISTS borrar_tokens');
    $db->query('CREATE EVENT borrar_tokens ON SCHEDULE EVERY 30 MINUTE STARTS CURRENT_TIMESTAMP
                DO DELETE FROM Tokens WHERE date < NOW() - INTERVAL 30 MINUTE');
} catch (mysqli_sql_exception $e) {
    $log('  No se pudo crear el evento (sin permiso EVENT). La app igual valida la expiración.');
}

$log('Migración terminada.');
