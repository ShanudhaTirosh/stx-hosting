module.exports = {
  apps: [
    {
      name: 'stx-discord-bot',
      script: 'dist/index.js',
      instances: 1, // Use dist/shard.js with 'auto' instances when sharding
      autorestart: true,
      watch: false,
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'production',
        PORT: 3050,
      },
    },
  ],
};
