import { z } from 'zod';
import dotenv from 'dotenv';

dotenv.config();

const isTest =
  process.env.NODE_ENV === 'test' ||
  process.argv.some((arg) => arg.includes('test')) ||
  process.env.npm_lifecycle_event === 'test';

const envSchema = z.object({
  NODE_ENV: z.enum(['development', 'production', 'test']).default(isTest ? 'test' : 'development'),
  PORT: z.coerce.number().default(3050),
  DISCORD_BOT_TOKEN: z.string().min(1, 'DISCORD_BOT_TOKEN is required').default(isTest ? 'dummy_test_token' : ''),
  DISCORD_CLIENT_ID: z.string().min(1, 'DISCORD_CLIENT_ID is required').default(isTest ? 'dummy_test_client_id' : ''),
  DISCORD_GUILD_ID: z.string().optional(),
  SUPABASE_URL: z.string().url('SUPABASE_URL must be a valid URL').default('http://localhost:8000/supabase'),
  SUPABASE_SERVICE_ROLE_KEY: z.string().min(1, 'SUPABASE_SERVICE_ROLE_KEY is required').default('dummy_service_role_key_for_testing'),
  BOT_INTERNAL_SECRET: z.string().default('default_internal_secret'),
  MAX_OPEN_TICKETS_PER_USER: z.coerce.number().default(2),
  DEFAULT_RAID_THRESHOLD_JOINS: z.coerce.number().default(10),
  DEFAULT_RAID_THRESHOLD_SECONDS: z.coerce.number().default(10),
  ORDER_RATE_LIMIT_ATTEMPTS: z.coerce.number().default(3),
  ORDER_RATE_LIMIT_WINDOW_SECONDS: z.coerce.number().default(900),
});

export type Env = z.infer<typeof envSchema>;

let parsedEnv: Env;

try {
  parsedEnv = envSchema.parse({
    NODE_ENV: isTest ? 'test' : process.env.NODE_ENV,
    DISCORD_BOT_TOKEN: process.env.DISCORD_BOT_TOKEN || (isTest ? 'dummy_test_token' : undefined),
    DISCORD_CLIENT_ID: process.env.DISCORD_CLIENT_ID || (isTest ? 'dummy_test_client_id' : undefined),
    ...process.env,
  });
} catch (error) {
  if (isTest) {
    parsedEnv = envSchema.parse({
      NODE_ENV: 'test',
      DISCORD_BOT_TOKEN: 'dummy_test_token',
      DISCORD_CLIENT_ID: 'dummy_test_client_id',
      SUPABASE_URL: 'http://localhost:8000/supabase',
      SUPABASE_SERVICE_ROLE_KEY: 'test_key',
    });
  } else {
    console.error('❌ Environment validation failed:', error);
    process.exit(1);
  }
}

export const env = parsedEnv;
