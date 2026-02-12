# 🎯 Guía: Cómo Ver Logs en el Web Profiler de Symfony

## 📋 Instrucciones Paso a Paso

### Método 1: Usar el Endpoint de Prueba (Recomendado)

#### Paso 1: Abre tu navegador
Navega a una de estas URLs:

**🔍 Ver todos los tipos de logs:**
```
http://localhost/test/monolog
```

**🤖 Ver solo logs del AI Agent:**
```
http://localhost/test/monolog/agent
```

#### Paso 2: Localiza la Barra de Debug de Symfony
Después de cargar la página, verás una **barra negra en la parte inferior** de la pantalla. Se ve así:

```
┌─────────────────────────────────────────────────────────────────┐
│  ▼ 200  🎯 /_profiler/...  ⚡ 123ms  💾 8MB  ✉️ 0  📝 8 logs    │
└─────────────────────────────────────────────────────────────────┘
```

¡Esta es la **Symfony Debug Toolbar**!

#### Paso 3: Abre el Profiler
Tienes **2 opciones**:

**Opción A:** Haz clic en el icono de **"📝 Logs"** en la barra
**Opción B:** Haz clic en el **logo de Symfony** (🎯) y luego ve a la pestaña "Logs"

#### Paso 4: Filtra por Canal
Una vez en la página de Logs:

1. Verás una tabla con TODOS los logs
2. **Busca el selector de canales** (dropdown o filtros en la parte superior)
3. Selecciona uno de estos canales:
   - **`ai_agent`** - Para ver logs del agente de IA
   - **`ai_tools`** - Para ver logs de herramientas (AddToCart, Search, etc.)
   - **`ai_context`** - Para ver logs de contexto de conversación

#### Paso 5: Explora los Logs
Verás algo como esto:

```
┌─────────────────────────────────────────────────────────────────┐
│ Channel: ai_agent                                                │
├──────────┬────────────────────────────────────────────────────┬─┤
│ Level    │ Message                          │ Context          │ │
├──────────┼────────────────────────────────────────────────────┼─┤
│ INFO     │ 🤖 Test: AI Agent started      │ {test_id: ...}   │ │
│ DEBUG    │ 🤖 Test: Processing request    │ {context_size: 5}│ │
│ ERROR    │ ❌ Test: Simulated error        │ {error: ...}     │ │
└──────────┴────────────────────────────────────────────────────┴─┘
```

#### Paso 6: Haz Clic en un Log para Ver Más Detalles
Al hacer clic en cualquier log, verás:
- **Mensaje completo**
- **Contexto estructurado** (arrays, objetos)
- **Stack trace** (en caso de errores)
- **Hora exacta**
- **Nivel de log**

---

## 🎬 Método 2: Usar el Chatbot Real

### Paso 1: Ve a tu aplicación
```
http://localhost
```

### Paso 2: Inicia sesión (si es necesario)
- Email: `admin@myshop.com` (o tu usuario)
- Password: tu contraseña

### Paso 3: Usa el Chatbot
Haz clic en el icono del chatbot y escribe algo como:
```
show me laptops for gaming
```

### Paso 4: Abre el Profiler
Después de recibir la respuesta del chatbot:
1. Busca la **barra de debug** en la parte inferior
2. Haz clic en **"Logs"**
3. Filtra por canal **`ai_agent`**

### Paso 5: Verás Logs REALES
Verás logs como estos:

```
🤖 AI AGENT CALL START
   - user_message: "show me laptops for gaming"
   - conversation_id: "abc-123"
   - messages_in_context: 5

🔧 Tool Calls Made
   - tool_calls: [
       {name: "SemanticProductSearchTool", arguments: {...}}
     ]

🤖 AI AGENT CALL END
   - response_type: "string"
   - execution_time_ms: 1500
```

---

## 📸 Capturas de Pantalla Explicadas

### Vista de la Barra de Debug
```
┌─────────────────────────────────────────────────────────────┐
│  Status  │  Route  │  Time  │  Memory  │  Logs  ← AQUÍ     │
│   200    │ /chat   │ 250ms  │   12MB   │   📝 15           │
└─────────────────────────────────────────────────────────────┘
                                              ↑
                          Número de logs capturados
```

### Vista del Profiler - Pestaña Logs
```
┌─────────────────────────────────────────────────────────────┐
│  [Performance] [Request] [Logs] [Events] [Cache] [...]      │ ← Pestañas
├─────────────────────────────────────────────────────────────┤
│  Filter by channel: [All ▼] [ai_agent ▼] [ai_tools ▼]      │ ← Filtros
├──────────┬───────────────────────────────────────┬──────────┤
│ Level    │ Message                    │ Channel  │ Context  │
├──────────┼───────────────────────────────────────┼──────────┤
│ INFO     │ 🤖 AI AGENT CALL START    │ ai_agent │ {...}    │
│ INFO     │ 🔧 Tool Calls Made        │ ai_agent │ {...}    │
│ INFO     │ 🔍 SemanticProductSearch  │ ai_tools │ {...}    │
└──────────┴───────────────────────────────────────┴──────────┘
```

---

## 🔍 Qué Información Verás

### Canal `ai_agent`
- ✅ Mensajes del usuario
- ✅ Inicio y fin de procesamiento del agente
- ✅ **Tool calls** con argumentos JSON completos
- ✅ Metadata de respuesta (modelo, tokens, tiempo)
- ✅ Errores con stack traces

### Canal `ai_tools`
- ✅ Nombre de la tool ejecutada
- ✅ Parámetros de entrada
- ✅ Resultados de validación
- ✅ Datos retornados
- ✅ Warnings y errores específicos de tools

### Canal `ai_context`
- ✅ Carga de contexto de conversación
- ✅ Mensajes guardados/recuperados de Redis
- ✅ Estado de la conversación

---

## 💡 Tips y Trucos

### Tip 1: Usar los Niveles de Log
En el filtro de logs, puedes filtrar por nivel:
- **DEBUG** - Información muy detallada
- **INFO** - Eventos importantes
- **WARNING** - Cosas inusuales
- **ERROR** - Errores que requieren atención

### Tip 2: Buscar Texto
Usa Ctrl+F en tu navegador para buscar:
- Nombres de productos
- IDs de conversación
- Mensajes específicos

### Tip 3: Ver JSON Formateado
Cuando veas contexto JSON en el profiler, el formato es automático y puedes:
- Expandir/colapsar objetos
- Copiar valores
- Ver tipos de datos

### Tip 4: Timeline
El profiler también muestra una **línea de tiempo** donde puedes ver:
- Cuándo se ejecutó cada log
- Duración de operaciones
- Orden de ejecución

---

## 🚨 Solución de Problemas

### ❌ "No veo la barra de debug"
**Causa:** No estás en modo desarrollo  
**Solución:** Verifica `APP_ENV=dev` en tu `.env`

### ❌ "La barra aparece pero está vacía"
**Causa:** El Web Profiler no está habilitado  
**Solución:**
```bash
docker-compose exec php php bin/console debug:config web_profiler
```
Debe mostrar `enabled: true`

### ❌ "No aparecen logs de ai_agent"
**Causa:** Los logs no se están generando  
**Solución:**
1. Ve a `/test/monolog` para generar logs de prueba
2. Verifica que el código use `$aiAgentLogger->info(...)`

### ❌ "Veo logs pero sin contexto"
**Causa:** Los logs se están generando sin el segundo parámetro  
**Solución:** El código debe ser:
```php
$logger->info('Mensaje', ['key' => 'value']); // ✅ Correcto
$logger->info('Mensaje'); // ❌ Sin contexto
```

---

## 📚 Recursos Adicionales

### Ver Logs en Tiempo Real (Terminal)
```bash
# Ver logs del agente
docker-compose exec php tail -f var/log/ai_agent.log | jq '.'

# Ver logs de tools
docker-compose exec php tail -f var/log/ai_tools.log | jq '.'
```

### Limpiar Cache Si No Ves Cambios
```bash
docker-compose exec php php bin/console cache:clear
```

### Ver Todas las Rutas Disponibles
```bash
docker-compose exec php php bin/console debug:router
```

---

## ✅ Checklist

Antes de reportar un problema, verifica:

- [ ] Estoy en modo desarrollo (`APP_ENV=dev`)
- [ ] El Web Profiler está habilitado
- [ ] Probé primero `/test/monolog` para generar logs
- [ ] La barra de debug aparece en la parte inferior
- [ ] He limpiado la caché
- [ ] Mi navegador no está en modo incógnito (puede ocultar la barra)

---

**¡Listo!** Ahora puedes ver todos los logs del AI Agent directamente en el Web Profiler de Symfony 🎉

---

## 🎯 Ejemplo de Flujo Completo

1. **Abre:** `http://localhost/test/monolog`
2. **Espera:** Ver el JSON de respuesta
3. **Mira abajo:** Barra de debug negra
4. **Haz clic:** Icono "📝 Logs" (muestra el número de logs)
5. **Filtra:** Selecciona canal "ai_agent"
6. **Explora:** Haz clic en cada log para ver detalles

¡Así de simple! 🚀
