# MyShop E2E Tests

Pruebas end-to-end usando Playwright para la aplicación MyShop.

## 🎯 Tests Disponibles

### Tests Originales (⚠️ No funcionales - requieren atributos data-test)
- `auth.spec.ts` - Autenticación (requiere data-test)
- `cart.spec.ts` - Carrito de compras (requiere data-test)
- `search.spec.ts` - Búsqueda de productos (requiere data-test)
- `checkout.spec.ts` - Proceso de checkout (requiere data-test)

### Tests Reales (✅ Funcionales con selectores actuales)
- `real-auth.spec.ts` - Autenticación con selectores reales
- `real-products.spec.ts` - Navegación y visualización de productos
- `real-cart.spec.ts` - Carrito usando API REST
- `real-chatbot.spec.ts` - Chatbot AI
- `real-checkout.spec.ts` - Checkout y órdenes

## 🚀 Ejecución

### Pre-requisitos
1. Tener los contenedores Docker corriendo:
   ```bash
   docker-compose up -d
   ```

2. La aplicación debe estar accesible en `http://localhost:8080`

### Instalar dependencias
```bash
cd tests/E2E
npm install
```

### Ejecutar todos los tests
```bash
npm test
```

### Ejecutar tests específicos
```bash
# Solo tests de autenticación
npm test -- real-auth.spec.ts

# Solo tests de carrito
npm test -- real-cart.spec.ts

# Solo tests de chatbot
npm test -- real-chatbot.spec.ts
```

### Ejecutar en modo headed (ver navegador)
```bash
npm run test:headed
```

### Ejecutar en modo UI (interfaz interactiva)
```bash
npm run test:ui
```

### Ejecutar en modo debug
```bash
npm run test:debug
```

### Ver reporte HTML
```bash
npm run report
```

### Ejecutar en navegador específico
```bash
# Solo Chrome
npx playwright test --project=chromium

# Solo Firefox
npx playwright test --project=firefox

# Solo Mobile Chrome
npx playwright test --project="Mobile Chrome"
```

## 📊 Cobertura de Tests

### ✅ Implementado y funcional

#### Autenticación (`real-auth.spec.ts`)
- ✅ Visualización de página de login
- ✅ Login exitoso con admin
- ✅ Login exitoso con customer
- ✅ Manejo de credenciales inválidas
- ✅ Funcionalidad "Remember me"
- ✅ Visualización de cuentas de prueba

#### Productos (`real-products.spec.ts`)
- ✅ Visualización de home page
- ✅ Lista de productos
- ✅ Navegación a detalle de producto
- ✅ Visualización de detalles
- ✅ Recomendaciones personalizadas (usuarios logueados)
- ✅ Productos destacados (usuarios anónimos)

#### Carrito (`real-cart.spec.ts`)
- ✅ Visualización de carrito vacío
- ✅ Agregar item via API
- ✅ Ver contenido del carrito
- ✅ Eliminar item via API
- ✅ Actualizar cantidad via API
- ✅ Limpiar carrito completo
- ✅ Persistencia de carrito después de logout/login

#### Chatbot (`real-chatbot.spec.ts`)
- ✅ Enviar mensaje al chatbot
- ✅ Búsqueda de productos via chatbot
- ✅ Consulta de estado de orden
- ✅ Mantener contexto de conversación
- ✅ Recuperar historial de conversación
- ✅ Limpiar conversación
- ✅ Reset de contexto

#### Checkout y Órdenes (`real-checkout.spec.ts`)
- ✅ Visualización de página checkout
- ✅ Crear orden via API
- ✅ Visualización de lista de órdenes
- ✅ Recuperar órdenes via API
- ✅ Obtener detalles de orden específica
- ✅ Navegación de carrito a checkout
- ✅ Confirmación de orden

### ❌ Falta implementar

#### Búsqueda semántica
- ❌ Búsqueda con lenguaje natural
- ❌ Filtros de categoría
- ❌ Filtros de precio
- ❌ Ordenamiento de resultados
- ❌ Tracking de búsquedas

#### Admin Panel
- ❌ Login como admin
- ❌ Gestión de productos
- ❌ Gestión de usuarios
- ❌ Métricas de búsqueda
- ❌ Preguntas sin respuesta del chatbot
- ❌ Asistente AI administrativo

#### Registro de usuarios
- ❌ Formulario de registro
- ❌ Validación de campos
- ❌ Confirmación de email
- ❌ Flujo completo de nuevo usuario

#### Performance
- ❌ Tiempo de carga de páginas
- ❌ Tiempo de respuesta de APIs
- ❌ Tamaño de recursos
- ❌ Métricas Core Web Vitals

## 🔧 Mejoras Recomendadas

### 1. Agregar atributos data-test a los templates
Para tests más confiables y mantenibles, agregar atributos `data-test` en:
- Botones de acción (login, add-to-cart, checkout, etc.)
- Formularios y campos de entrada
- Mensajes de error y éxito
- Elementos de navegación

**Ejemplo:**
```twig
<button type="submit" data-test="login-button" class="btn btn-primary">
    Sign In
</button>

<div class="alert alert-danger" data-test="error-message" role="alert">
    {{ error.messageKey|trans(error.messageData, 'security') }}
</div>
```

### 2. Agregar fixtures de datos
Crear datos de prueba consistentes:
- Productos de prueba
- Usuarios de prueba
- Órdenes de prueba

### 3. Agregar tests visuales
- Screenshot comparison
- Visual regression tests

### 4. Tests de accesibilidad
- axe-core integration
- ARIA labels validation
- Keyboard navigation

### 5. Tests de performance
- Lighthouse CI integration
- Core Web Vitals monitoring

### 6. CI/CD Integration
Agregar a pipeline de CI/CD para ejecutar tests automáticamente:
```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: actions/setup-node@v2
      - name: Install dependencies
        run: cd tests/E2E && npm install
      - name: Install Playwright Browsers
        run: cd tests/E2E && npx playwright install --with-deps
      - name: Run E2E tests
        run: cd tests/E2E && npm test
      - uses: actions/upload-artifact@v2
        if: always()
        with:
          name: playwright-report
          path: tests/E2E/playwright-report/
```

## 🐛 Troubleshooting

### Error: "Connection refused"
- Verificar que Docker containers estén corriendo
- Verificar que nginx esté en puerto 8080

### Error: "Timeout waiting for selector"
- El elemento puede no existir o tardar en cargar
- Aumentar timeout o agregar waits explícitos

### Tests fallan de forma intermitente
- Agregar `waitForLoadState('networkidle')`
- Usar selectores más específicos
- Verificar estado de la aplicación antes de hacer assertions

## 📝 Cuentas de Prueba

Según los templates, estas cuentas están disponibles:

- **Admin**: `admin@example.com` / `admin123`
- **Seller**: `seller@example.com` / `seller123`  
- **Customer**: `customer1@example.com` / `customer123`

## 📚 Recursos

- [Playwright Documentation](https://playwright.dev/)
- [Test Best Practices](https://playwright.dev/docs/best-practices)
- [Test Selectors](https://playwright.dev/docs/selectors)
