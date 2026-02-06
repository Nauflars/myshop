# Información sobre Usuario Administrador

## Descripción General

El usuario administrador tiene acceso a funcionalidades exclusivas dentro del chatbot, específicamente estadísticas del negocio y métricas de rendimiento. Este documento explica cómo acceder, qué funcionalidades están disponibles, y cómo configurar usuarios admin.

---

## Credenciales de Acceso

### Por Defecto (Fixtures)

Después de cargar los fixtures, el usuario administrador se crea automáticamente con estas credenciales:

```
Email: admin@myshop.com
Password: admin123
Rol: ROLE_ADMIN
```

### Cómo Iniciar Sesión

1. **Navegar a la página de login**:
   ```
   http://localhost/login
   ```

2. **Introducir credenciales**:
   - Email: `admin@myshop.com`
   - Password: `admin123`

3. **Acceder al chatbot**:
   - Después de autenticar, abrir el widget de chatbot en cualquier página
   - El chatbot detecta automáticamente que eres administrador

---

## Funcionalidades Exclusivas de Admin

### 1. Estadísticas del Negocio (GetAdminStatsTool)

**Cómo usar**:
```
Usuario Admin: "¿Cuáles son las estadísticas de ventas?"
Usuario Admin: "Muéstrame las estadísticas del mes"
Usuario Admin: "Dame un resumen del negocio"
```

**Información devuelta**:
- 📊 **Total de ventas del mes**: Suma de todas las ventas en el mes actual
- 🏆 **Productos más vendidos**: Top 5 productos por cantidad vendida
- 👥 **Usuarios activos**: Número de usuarios que han iniciado sesión en los últimos 30 días
- 📦 **Órdenes pendientes**: Número de órdenes con estado PENDING

**Formato de respuesta**:
```
Estadísticas del Negocio (Febrero 2026):

📊 Total de Ventas: $45,230.50 USD

🏆 Productos Más Vendidos:
   1. iPhone 15 Pro Max - 23 unidades
   2. MacBook Pro M3 - 15 unidades
   3. AirPods Pro - 42 unidades
   4. Apple Watch Series 9 - 18 unidades
   5. iPad Air - 12 unidades

👥 Usuarios Activos: 342 usuarios
📦 Órdenes Pendientes: 7 órdenes
```

**Cache**: Las estadísticas se cachean por 5 minutos para optimizar rendimiento.

---

### 2. Validación de Permisos

**Qué pasa si un usuario regular intenta acceder**:
```
Usuario Regular: "¿Cuáles son las estadísticas?"
Chatbot: "Lo siento, no tienes permisos para ver esta información. Las estadísticas solo están disponibles para administradores."
```

**Implementación técnica**:
```php
// src/Infrastructure/AI/Tool/GetAdminStatsTool.php
public function __invoke(): array
{
    if (!$this->security->isGranted('ROLE_ADMIN')) {
        return [
            'success' => false,
            'message' => 'No tienes permisos para ver esta información.',
        ];
    }
    
    // ... lógica de estadísticas
}
```

---

## Cómo Crear Nuevos Usuarios Admin

### Opción 1: Fixtures (Desarrollo)

Editar `src/DataFixtures/AppFixtures.php`:

```php
// Crear administrador
$admin = new User();
$admin->setEmail('admin@myshop.com');
$admin->setPassword(
    $this->passwordHasher->hashPassword($admin, 'admin123')
);
$admin->setRoles(['ROLE_ADMIN']);
$manager->persist($admin);

// Crear otro admin
$superAdmin = new User();
$superAdmin->setEmail('superadmin@myshop.com');
$superAdmin->setPassword(
    $this->passwordHasher->hashPassword($superAdmin, 'super123')
);
$superAdmin->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
$manager->persist($superAdmin);

$manager->flush();
```

**Cargar fixtures**:
```bash
docker-compose exec php bin/console doctrine:fixtures:load
```

### Opción 2: Comando de Consola (Producción)

Crear comando personalizado `bin/console app:create-admin`:

```php
// src/Command/CreateAdminCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateAdminCommand extends Command
{
    protected static $defaultName = 'app:create-admin';
    
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasher $passwordHasher
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        $emailQuestion = new Question('Email: ');
        $email = $helper->ask($input, $output, $emailQuestion);
        
        $passwordQuestion = new Question('Password: ');
        $passwordQuestion->setHidden(true);
        $password = $helper->ask($input, $output, $passwordQuestion);
        
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_ADMIN']);
        
        $this->userRepository->save($user);
        
        $output->writeln('<info>Admin user created successfully!</info>');
        
        return Command::SUCCESS;
    }
}
```

**Usar**:
```bash
docker-compose exec php bin/console app:create-admin
```

### Opción 3: Panel de Administración (Web)

*(Futuro - no implementado en spec-003)*

---

## Configuración de Seguridad

### config/packages/security.yaml

Asegurar que ROLE_ADMIN está configurado correctamente:

```yaml
security:
    role_hierarchy:
        ROLE_ADMIN: [ROLE_USER]
        ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]
    
    providers:
        app_user_provider:
            entity:
                class: App\Domain\Entity\User
                property: email
    
    firewalls:
        main:
            lazy: true
            provider: app_user_provider
            form_login:
                login_path: login
                check_path: login
            logout:
                path: logout
```

---

## Verificación de Acceso

### Test Manual

1. **Login como admin**:
   ```bash
   # Abrir en navegador
   http://localhost/login
   # Email: admin@myshop.com
   # Password: admin123
   ```

2. **Abrir chatbot**:
   ```
   Click en widget de chatbot
   ```

3. **Preguntar por estadísticas**:
   ```
   "¿Cuáles son las estadísticas?"
   ```

4. **Verificar respuesta**:
   - Debe mostrar datos de ventas, productos, usuarios, órdenes
   - NO debe mostrar mensaje de error de permisos

### Test Automatizado

```php
// tests/Integration/GetAdminStatsToolTest.php
public function test_admin_can_access_statistics(): void
{
    // Arrange: Create admin user
    $admin = new User();
    $admin->setEmail('test.admin@example.com');
    $admin->setRoles(['ROLE_ADMIN']);
    $this->entityManager->persist($admin);
    $this->entityManager->flush();
    
    // Act: Login as admin and call tool
    $this->client->loginUser($admin);
    $tool = $this->container->get(GetAdminStatsTool::class);
    $result = $tool();
    
    // Assert: Should receive statistics
    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('totalSales', $result['data']);
    $this->assertArrayHasKey('topProducts', $result['data']);
    $this->assertArrayHasKey('activeUsers', $result['data']);
    $this->assertArrayHasKey('pendingOrders', $result['data']);
}

public function test_regular_user_cannot_access_statistics(): void
{
    // Arrange: Create regular user
    $user = new User();
    $user->setEmail('test.user@example.com');
    $user->setRoles(['ROLE_USER']);
    $this->entityManager->persist($user);
    $this->entityManager->flush();
    
    // Act: Login as regular user and call tool
    $this->client->loginUser($user);
    $tool = $this->container->get(GetAdminStatsTool::class);
    $result = $tool();
    
    // Assert: Should receive permission denied
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('No tienes permisos', $result['message']);
}
```

---

## Queries de Estadísticas

### SQL Ejecutados por GetAdminStats

**Total de ventas del mes**:
```sql
SELECT SUM(total_in_cents) / 100 AS total_sales
FROM orders
WHERE status IN ('CONFIRMED', 'SHIPPED', 'DELIVERED')
  AND YEAR(created_at) = YEAR(CURRENT_DATE())
  AND MONTH(created_at) = MONTH(CURRENT_DATE());
```

**Productos más vendidos**:
```sql
SELECT p.name, SUM(oi.quantity) AS units_sold
FROM order_items oi
JOIN products p ON oi.product_id = p.id
JOIN orders o ON oi.order_id = o.id
WHERE o.status IN ('CONFIRMED', 'SHIPPED', 'DELIVERED')
  AND YEAR(o.created_at) = YEAR(CURRENT_DATE())
  AND MONTH(o.created_at) = MONTH(CURRENT_DATE())
GROUP BY p.id, p.name
ORDER BY units_sold DESC
LIMIT 5;
```

**Usuarios activos**:
```sql
SELECT COUNT(DISTINCT id)
FROM users
WHERE last_login_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY);
```

**Órdenes pendientes**:
```sql
SELECT COUNT(*)
FROM orders
WHERE status = 'PENDING';
```

---

## Troubleshooting

### Problema: "No tienes permisos para ver esta información"

**Causa**: Usuario no tiene rol ROLE_ADMIN

**Solución**:
```bash
# Verificar roles en base de datos
docker-compose exec mysql mysql -u root -proot -D myshop -e "SELECT email, roles FROM users WHERE email = 'admin@myshop.com';"

# Si no tiene ROLE_ADMIN, actualizar:
docker-compose exec mysql mysql -u root -proot -D myshop -e "UPDATE users SET roles = '[\"ROLE_ADMIN\"]' WHERE email = 'admin@myshop.com';"
```

### Problema: Estadísticas siempre en cero

**Causa**: No hay datos de órdenes/productos en la base de datos

**Solución**:
```bash
# Cargar fixtures con datos de prueba
docker-compose exec php bin/console doctrine:fixtures:load
```

### Problema: Usuario admin no puede hacer login

**Causa**: Password hasheada incorrectamente

**Solución**:
```bash
# Rehashear password
docker-compose exec php bin/console security:hash-password admin123

# Copiar hash generado y actualizar en DB:
docker-compose exec mysql mysql -u root -proot -D myshop -e "UPDATE users SET password = '<HASH>' WHERE email = 'admin@myshop.com';"
```

---

## Próximas Funcionalidades de Admin (Fuera de alcance spec-003)

### Posibles mejoras futuras:
- 📊 **Gráficos**: Visualización de ventas por día/semana
- 📈 **Comparativas**: Mes actual vs mes anterior
- 🔍 **Filtros**: Estadísticas por categoría de producto
- 📧 **Reportes**: Enviar estadísticas por email automáticamente
- 👥 **Gestión de usuarios**: Crear/editar/eliminar usuarios desde chatbot
- 🛠️ **Gestión de productos**: Añadir/modificar productos vía chat

---

## Referencias

- **Use Case**: `src/Application/UseCase/AI/GetAdminStats.php` (T059-T064)
- **AI Tool**: `src/Infrastructure/AI/Tool/GetAdminStatsTool.php` (T065-T069)
- **Tests**: `tests/Integration/GetAdminStatsToolTest.php` (T072-T074)
- **Fixtures**: `src/DataFixtures/AppFixtures.php` (T085-T088)
- **Spec Document**: [spec.md](spec.md) - US5: Estadísticas para Administradores

---

## Resumen

✅ **Email**: admin@myshop.com  
✅ **Password**: admin123  
✅ **Funcionalidad**: Estadísticas del negocio vía chatbot  
✅ **Comando**: "¿Cuáles son las estadísticas?"  
✅ **Seguridad**: Validación ROLE_ADMIN obligatoria  
✅ **Cache**: 5 minutos de duración
