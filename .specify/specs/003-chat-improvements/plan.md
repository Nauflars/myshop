# Implementation Plan: Chat Assistant Improvements

## Overview

Mejoras críticas al asistente de chat conversacional para eliminar la necesidad de IDs de usuario explícitos, implementar persistencia de conversación, y proporcionar funcionalidades de administración. Estas mejoras harán que el chatbot sea más natural, seguro y útil tanto para clientes como administradores.

## Technical Stack

- **Backend**: Symfony 7, PHP 8.3
- **AI Framework**: Symfony AI Bundle (ya implementado en spec 002)
- **Security**: Symfony Security Component para contexto de autenticación
- **Storage**: 
  - MySQL 8.0 (datos persistentes)
  - Doctrine ORM (conversaciones en base de datos)
- **Frontend**: JavaScript vanilla, AJAX para chatbot widget

## Architecture

### Capas Afectadas (DDD-Compliant)

```
src/
├── Application/
│   └── UseCase/
│       └── AI/
│           ├── AddToCartByName.php         # Ya existe (Fase 15 spec-002)
│           ├── GetCartSummary.php          # Ya existe (Fase 15 spec-002)
│           ├── CreateOrder.php             # Ya existe (Fase 15 spec-002)
│           ├── GetAdminStats.php           # NUEVO - estadísticas admin
│           └── Conversation/               # NUEVO - gestión de conversaciones
│               ├── SaveConversation.php
│               ├── LoadConversation.php
│               ├── ClearConversation.php
│               └── ListUserConversations.php
├── Domain/
│   ├── Entity/
│   │   ├── Conversation.php                # NUEVO - hilo de chat persistente
│   │   └── ConversationMessage.php         # NUEVO - mensajes individuales
│   └── Repository/
│       └── ConversationRepositoryInterface.php  # NUEVO
├── Infrastructure/
│   ├── AI/
│   │   ├── Tool/
│   │   │   ├── AddToCartTool.php           # ACTUALIZAR - quitar userId param
│   │   │   ├── GetCartSummaryTool.php      # Ya correcto (usa Security)
│   │   │   ├── CreateOrderTool.php         # Ya correcto (usa Security)
│   │   │   ├── GetAdminStatsTool.php       # NUEVO - solo admin
│   │   │   ├── ClearConversationTool.php   # NUEVO - borrar contexto
│   │   │   └── GetUserInfoTool.php         # NUEVO - info usuario actual
│   │   └── Service/
│   │       └── ConversationManager.php     # NUEVO - gestión de contexto
│   ├── Controller/
│   │   ├── ChatbotController.php           # ACTUALIZAR - cargar/guardar contexto
│   │   └── AdminController.php             # VERIFICAR - acceso admin
│   └── Repository/
│       └── DoctrineConversationRepository.php  # NUEVO
├── templates/
│   └── chatbot/
│       └── widget.html.twig                # ACTUALIZAR - botón limpiar chat
└── public/
    └── js/
        └── chatbot.js                      # ACTUALIZAR - persistencia frontend
```

## Project Structure

### Entidades de Dominio

**Conversation** (Nueva entidad)
- `id`: UUID
- `user`: Relación con User
- `title`: String generado automáticamente del primer mensaje
- `createdAt`: DateTime
- `updatedAt`: DateTime
- `messages`: Collection<ConversationMessage>

**ConversationMessage** (Nueva entidad)
- `id`: UUID
- `conversation`: Relación con Conversation
- `role`: enum (user, assistant, system)
- `content`: Text
- `toolCalls`: JSON nullable (registro de herramientas usadas)
- `timestamp`: DateTime

### Casos de Uso

1. **AddToCartByName** (Ya existe - Fase 15)
   - Usa `Security::getUser()` en lugar de userId explícito
   - Validación: usuario autenticado
   
2. **GetCartSummary** (Ya existe - Fase 15)
   - Obtiene carrito del usuario autenticado
   - Devuelve items, cantidades, precios, total
   
3. **CreateOrder** (Ya existe - Fase 15)
   - Crea orden para usuario autenticado
   - Limpia carrito después de confirmar

4. **GetAdminStats** (Nuevo)
   - Requiere rol ROLE_ADMIN
   - Devuelve: total ventas, productos más vendidos, usuarios activos, órdenes pendientes
   
5. **SaveConversation** (Nuevo)
   - Guarda mensaje en conversación activa
   - Crea nueva conversación si no existe
   
6. **LoadConversation** (Nuevo)
   - Carga historial completo de conversación
   - Filtra por usuario autenticado
   
7. **ClearConversation** (Nuevo)
   - Marca conversación como archivada o la elimina
   - Solo el propietario puede limpiar

8. **ListUserConversations** (Nuevo)
   - Lista todas las conversaciones del usuario
   - Ordenadas por fecha

### Herramientas AI

1. **AddToCartTool** (Actualizar)
   - Parámetros: `productName`, `quantity`
   - Sin parámetro `userId` (usa Security)
   
2. **GetCartSummaryTool** (Ya correcto - Fase 15)
   - Sin parámetros (usa Security)
   
3. **CreateOrderTool** (Ya correcto - Fase 15)
   - Sin parámetro `userId` (usa Security)
   
4. **GetAdminStatsTool** (Nuevo)
   - Sin parámetros
   - Autorización: solo ROLE_ADMIN
   - Devuelve estadísticas en español
   
5. **ClearConversationTool** (Nuevo)
   - Sin parámetros (limpia conversación actual del usuario)
   - Mensaje: "He limpiado nuestro historial de conversación"
   
6. **GetUserInfoTool** (Nuevo)
   - Sin parámetros
   - Devuelve: nombre, email, rol, conversaciones activas

## Libraries

### Core Dependencies (Ya instaladas)
```bash
symfony/security-bundle          # Para Security context
symfony/ai-bundle               # Symfony AI
doctrine/orm                    # Persistencia
```

### Configuración

**config/packages/security.yaml** (Verificar configuración)
- Asegurar que admin tiene ROLE_ADMIN
- Firewall configurado para /api/chat

**config/packages/doctrine.yaml**
- Agregar mapping para nuevas entidades Conversation/ConversationMessage

**config/packages/ai.yaml** (Actualizar)
- Agregar nuevas herramientas al system prompt
- Instrucciones sobre cuándo usar herramientas de admin
- Instrucciones sobre persistencia de contexto

## Implementation Philosophy

### DDD Compliance
- **Domain Layer**: Entidades Conversation/ConversationMessage con lógica de negocio
- **Application Layer**: Use Cases orquestan persistencia y recuperación
- **Infrastructure Layer**: Repositorios Doctrine, Controllers, Tools AI

### Security-First
- **Autenticación implícita**: Todos los tools usan `Security::getUser()`
- **Autorización explícita**: GetAdminStatsTool valida ROLE_ADMIN
- **Aislamiento de datos**: Cada usuario solo ve sus conversaciones

### User Experience
- **Persistencia automática**: Cada mensaje se guarda en DB
- **Contexto continuo**: El chatbot "recuerda" conversaciones anteriores
- **Control del usuario**: Botón "Limpiar chat" visible en widget
- **Feedback claro**: Mensajes en español sobre acciones (limpiar, admin stats, etc.)

## Data Model

### Conversation Entity

```php
class Conversation
{
    private string $id;                    // UUID
    private User $user;                    // Propietario
    private string $title;                 // Auto-generado
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    private Collection $messages;          // ConversationMessage[]
    
    public function addMessage(ConversationMessage $message): void
    public function getLastMessage(): ?ConversationMessage
    public function getMessageCount(): int
    public function generateTitle(): string  // Del primer mensaje del usuario
}
```

### ConversationMessage Entity

```php
class ConversationMessage
{
    private string $id;                    // UUID
    private Conversation $conversation;
    private string $role;                  // user|assistant|system
    private string $content;
    private ?array $toolCalls;             // JSON: [{"tool": "AddToCart", "params": {...}}]
    private \DateTimeImmutable $timestamp;
}
```

## Dependencies

### Implementación Order
1. **Fase 1**: Entidades de conversación (Conversation, ConversationMessage)
2. **Fase 2**: Repositorios y migraciones de DB
3. **Fase 3**: Use Cases de gestión de conversación
4. **Fase 4**: ConversationManager service (capa Infrastructure)
5. **Fase 5**: Actualizar ChatbotController para persistir mensajes
6. **Fase 6**: Nuevos AI Tools (GetAdminStats, ClearConversation, GetUserInfo)
7. **Fase 7**: Frontend - botón limpiar chat y carga de contexto
8. **Fase 8**: Tests y documentación

### External Dependencies
- Ninguna nueva (todas las librerías ya están instaladas)

## Testing Strategy

### Unit Tests
- Use Cases: SaveConversation, LoadConversation, ClearConversation, GetAdminStats
- Entities: Conversation->addMessage(), generateTitle()

### Integration Tests
- ConversationRepository: save, findByUser, findActiveForUser
- ChatbotController: persistencia automática de mensajes
- Security: GetAdminStatsTool solo accesible por ROLE_ADMIN

### E2E Tests
- Conversación completa: usuario envía mensajes → se guardan → se cargan al recargar página
- Botón limpiar: usuario limpia chat → historial se borra → nueva conversación empieza
- Admin: usuario admin pregunta por estadísticas → recibe datos

## Implementation Notes

### Credenciales Admin (Verificar)
Necesitamos verificar o crear usuario admin en fixtures:
```php
// src/DataFixtures/AppFixtures.php
$admin = new User();
$admin->setEmail('admin@myshop.com');
$admin->setPassword($hashedPassword);
$admin->setRoles(['ROLE_ADMIN']);
```

### Migraciones Necesarias
```sql
CREATE TABLE conversations (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE conversation_messages (
    id VARCHAR(36) PRIMARY KEY,
    conversation_id VARCHAR(36) NOT NULL,
    role VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    tool_calls JSON,
    timestamp DATETIME NOT NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);
```

### Frontend Changes
- `chatbot.js`: Guardar `conversationId` en localStorage
- Enviar `conversationId` en cada request a `/api/chat`
- Botón "🗑️ Limpiar chat" que llama a `ClearConversationTool`
- Al cargar página: recuperar mensajes anteriores si existe conversationId

## Success Criteria

1. ✅ Usuario puede añadir productos al carrito sin especificar su ID
2. ✅ Usuario puede listar su carrito y ver precio total
3. ✅ Conversaciones persisten entre sesiones (hasta que el usuario las borre)
4. ✅ Usuario admin puede preguntar "¿Cuáles son las estadísticas?" y recibir datos
5. ✅ Usuario puede limpiar el chat con un botón
6. ✅ Toda la funcionalidad en español

## Risks & Mitigations

| Riesgo | Impacto | Mitigación |
|--------|---------|------------|
| Conversaciones crecen indefinidamente | Alto storage cost | Implementar límite de mensajes por conversación (ej: últimos 50) |
| Usuario no autenticado intenta usar chat | Error en Tools | Validar autenticación en ChatbotController antes de llamar agent |
| Admin stats son lentos | Mala UX | Cachear estadísticas por 5 minutos, usar queries optimizadas |

## Timeline Estimate

- **Fase 1-2** (Entidades + Repos): 2-4 horas
- **Fase 3-5** (Use Cases + Services + Controller): 3-5 horas
- **Fase 6** (AI Tools): 1-2 horas
- **Fase 7** (Frontend): 2-3 horas
- **Fase 8** (Tests): 3-4 horas

**Total: 11-18 horas de desarrollo**
