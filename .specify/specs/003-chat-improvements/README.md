# Chat Assistant Improvements (003)

## Descripción General

Esta especificación mejora el asistente de chat conversacional implementado en **spec-002** para resolver problemas críticos de UX y seguridad:

1. ✅ **Autenticación implícita**: Eliminar IDs de usuario explícitos en operaciones del chat
2. ✅ **Persistencia de conversaciones**: Historial guardado automáticamente en base de datos
3. ✅ **Control del usuario**: Botón para limpiar/reiniciar conversación
4. ✅ **Funcionalidades admin**: Acceso a estadísticas del negocio vía chatbot
5. ✅ **Información del usuario**: Consultar datos de la cuenta actual

---

## Problemas Resueltos

### ❌ Antes
```
Usuario: "añade iPhone al carrito"
Chatbot: "Para añadir productos, necesito tu ID de usuario"
Usuario: "¿cuál es mi ID?"
Chatbot: "Lo siento, no tengo acceso a esa información"
```

### ✅ Después
```
Usuario: "añade iPhone al carrito"
Chatbot: "Perfecto, he añadido iPhone 15 Pro Max (x1) a tu carrito. Total: $1,199.00 USD"
```

---

## User Stories

| ID | Título | Prioridad | Estado |
|----|--------|-----------|--------|
| US1 | Añadir al carrito sin ID de usuario | P1 | ⏳ Pendiente |
| US2 | Ver carrito y precio total | P1 | ⏳ Pendiente |
| US3 | Persistencia de conversaciones | P1 | ⏳ Pendiente |
| US4 | Limpiar historial de conversación | P1 | ⏳ Pendiente |
| US5 | Estadísticas para administradores | P2 | ⏳ Pendiente |
| US6 | Información del usuario actual | P2 | ⏳ Pendiente |
| US7 | Acceso administrador (documentación) | P3 | ⏳ Pendiente |

---

## Dependencias

### Requeridas
- **spec-002**: AI Shopping Assistant debe estar implementado
- Symfony Security Bundle configurado
- Doctrine ORM funcional
- MySQL 8.0 corriendo en Docker

### Opcionales
- Ninguna

---

## Arquitectura

### Nuevas Entidades (Domain Layer)
```
src/Domain/Entity/
├── Conversation.php              # Nueva - hilo de chat
└── ConversationMessage.php       # Nueva - mensaje individual
```

### Nuevos Use Cases (Application Layer)
```
src/Application/UseCase/AI/
├── GetAdminStats.php             # Nueva - estadísticas admin
└── Conversation/
    ├── SaveConversation.php      # Nueva - guardar mensajes
    ├── LoadConversation.php      # Nueva - cargar historial
    ├── ClearConversation.php     # Nueva - limpiar chat
    └── ListUserConversations.php # Nueva - listar conversaciones
```

### Nuevas Herramientas AI (Infrastructure Layer)
```
src/Infrastructure/AI/Tool/
├── GetAdminStatsTool.php         # Nueva - solo ROLE_ADMIN
├── ClearConversationTool.php     # Nueva - reiniciar chat
└── GetUserInfoTool.php           # Nueva - info cuenta actual
```

### Actualizaciones
```
src/Infrastructure/Controller/
└── ChatbotController.php         # MODIFICAR - persistencia automática

public/js/
└── chatbot.js                    # MODIFICAR - cargar historial

templates/chatbot/
└── widget.html.twig              # MODIFICAR - botón limpiar
```

---

## Tareas por User Story

### US1: Añadir al carrito sin ID (6 tareas)
**Verificación**: AddToCartTool ya usa Security (implementado en spec-002 Fase 15)

### US2: Ver carrito y precio total (5 tareas)
**Verificación**: GetCartSummaryTool ya existe y funciona

### US3: Persistencia de conversaciones (20 tareas)
**Core**: Entidades, repositorios, ConversationManager, ChatbotController, frontend

### US4: Limpiar historial (12 tareas)
**Core**: ClearConversation use case, ClearConversationTool, botón frontend

### US5: Estadísticas admin (16 tareas)
**Core**: GetAdminStats use case, GetAdminStatsTool, validación ROLE_ADMIN, cache

### US6: Info del usuario (10 tareas)
**Core**: GetUserInfoTool, ConversationRepository->countByUser()

### US7: Documentación admin (12 tareas)
**Core**: Fixtures admin user, README.md, ADMIN_FEATURES.md

---

## Implementación

### Orden Recomendado
1. **Phase 1-2**: Setup + Entidades de conversación (8 horas)
2. **Phase 3-4**: Verificación US1/US2 (1 hora)
3. **Phase 5**: Persistencia US3 (4 horas)
4. **Phase 6**: Clear chat US4 (2 horas)
5. **Phase 7-9**: Admin + UserInfo + Docs (3 horas)
6. **Phase 10**: Tests y polish (4 horas)

**Total estimado**: 22 horas

### MVP (Mínimo viable)
- Phase 1-6 (US1-US4): Seguridad + persistencia + control
- **Tiempo**: 15 horas

---

## Testing

### Unit Tests
- Conversation entity (generateTitle, addMessage)
- ConversationMessage entity
- Use Cases (SaveConversation, LoadConversation, ClearConversation, GetAdminStats)

### Integration Tests
- ConversationRepository CRUD
- ChatbotController persistencia automática
- Security: GetAdminStatsTool solo ROLE_ADMIN
- Conversation isolation (user A no ve conversaciones de user B)

### E2E Tests
- Flujo completo con persistencia
- Add to cart → view cart → checkout (sin IDs)
- Clear chat → nueva conversación
- Admin stats access y validación permisos

---

## Credenciales de Prueba

### Usuario Regular
```
Email: user@example.com
Password: password
```

### Usuario Administrador
```
Email: admin@myshop.com
Password: admin123
```

*(Credenciales creadas en fixtures - T085-T088)*

---

## Comandos Útiles

### Crear migraciones
```bash
docker-compose exec php bin/console make:migration
docker-compose exec php bin/console doctrine:migrations:migrate
```

### Cargar fixtures
```bash
docker-compose exec php bin/console doctrine:fixtures:load
```

### Tests
```bash
# Unit tests
docker-compose exec php bin/phpunit tests/Domain/
docker-compose exec php bin/phpunit tests/Application/

# Integration tests
docker-compose exec php bin/phpunit tests/Integration/

# E2E tests
docker-compose exec php bin/phpunit tests/E2E/

# All tests
docker-compose exec php bin/phpunit
```

### Cache
```bash
docker-compose exec php bin/console cache:clear
```

---

## Documentos

- 📄 **[plan.md](plan.md)**: Plan técnico completo
- 📄 **[spec.md](spec.md)**: User stories y acceptance criteria
- 📄 **[tasks.md](tasks.md)**: 120 tareas organizadas por fase
- 📄 **README.md**: Este archivo

---

## Estado Actual

**Creado**: 2026-02-06  
**Estado**: 📝 Diseño completo, pendiente implementación  
**Bloqueadores**: Ninguno (spec-002 debe estar implementado)

---

## Contacto

Para preguntas sobre esta especificación, consultar:
- Documentos de diseño en esta carpeta
- Código existente en spec-002 (AI Shopping Assistant)
- README.md principal del proyecto
