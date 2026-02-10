// Initialize user_embeddings collection in MongoDB
db = db.getSiblingDB('myshop');

// Create collection with validation schema
db.createCollection('user_embeddings', {
  validator: {
    $jsonSchema: {
      bsonType: 'object',
      required: ['user_id', 'vector', 'last_updated_at', 'version'],
      properties: {
        user_id: {
          bsonType: 'int',
          description: 'User identifier (required, unique)'
        },
        vector: {
          bsonType: 'array',
          minItems: 1536,
          maxItems: 1536,
          items: {
            bsonType: 'double',
            description: '1536-dimensional embedding vector'
          }
        },
        last_updated_at: {
          bsonType: 'string',
          description: 'ISO 8601 timestamp of last update'
        },
        version: {
          bsonType: 'int',
          minimum: 1,
          description: 'Optimistic locking version'
        },
        created_at: {
          bsonType: ['string', 'null'],
          description: 'ISO 8601 timestamp of creation'
        }
      }
    }
  }
});

print('✓ Collection user_embeddings created');

// Create unique index on user_id
db.user_embeddings.createIndex(
  { user_id: 1 },
  { unique: true, name: 'idx_user_id' }
);
print('✓ Created unique index: idx_user_id');

// Create index on last_updated_at for stale queries
db.user_embeddings.createIndex(
  { last_updated_at: 1 },
  { name: 'idx_last_updated' }
);
print('✓ Created index: idx_last_updated');

// Create compound index for optimistic locking
db.user_embeddings.createIndex(
  { user_id: 1, version: 1 },
  { name: 'idx_user_version' }
);
print('✓ Created compound index: idx_user_version');

// Show collection stats
var stats = db.user_embeddings.stats();
print('\n=== Collection Statistics ===');
print('Database: myshop');
print('Collection: user_embeddings');
print('Document count: ' + stats.count);
print('Storage size: ' + (stats.storageSize / 1024).toFixed(2) + ' KB');
print('Index count: ' + stats.nindexes);
print('Total index size: ' + (stats.totalIndexSize / 1024).toFixed(2) + ' KB');

// List all indexes
print('\n=== Indexes ===');
db.user_embeddings.getIndexes().forEach(function(index) {
  print('- ' + index.name + ': ' + JSON.stringify(index.key) + (index.unique ? ' (unique)' : ''));
});

print('\n✅ MongoDB initialization completed successfully!');
