#!/bin/bash

echo "=== Verificación de búsquedas y perfil ==="
echo ""

# 1. Verificar búsquedas en MySQL
echo "📋 Búsquedas guardadas en MySQL:"
docker exec myshop_mysql mysql -uroot -prootpassword myshop -e "
SELECT 
    HEX(user_id) as user_id_hex,
    query, 
    mode,
    created_at 
FROM search_history 
WHERE user_id = UNHEX('48E672E148CA44DA81E9BE7EF2C712FC') 
ORDER BY created_at DESC 
LIMIT 10;
" 2>/dev/null

echo ""
echo "📦 Perfil en MongoDB:"
docker exec myshop_mongodb mongosh "mongodb://root:rootpassword@localhost:27017/myshop?authSource=admin" --quiet --eval "
var profile = db.user_profiles.findOne(
    {userId: '48e672e1-48ca-44da-81e9-be7ef2c712fc'},
    {userId: 1, lastUpdated: 1, 'dataSnapshot.recentSearches': 1}
);
if (profile) {
    print('✅ Perfil encontrado');
    print('📅 Última actualización:', profile.lastUpdated || 'N/A');
    print('🔍 Búsquedas recientes:');
    if (profile.dataSnapshot && profile.dataSnapshot.recentSearches) {
        profile.dataSnapshot.recentSearches.forEach(function(search, index) {
            print('  ' + (index + 1) + '. ' + search);
        });
    } else {
        print('  (ninguna)');
    }
} else {
    print('❌ Perfil no encontrado');
}
"

echo ""
echo "🗑️ Cache de Redis:"
docker exec myshop_redis redis-cli KEYS "*48e672e1-48ca-44da-81e9-be7ef2c712fc*"

echo ""
echo "✅ Verificación completa"
