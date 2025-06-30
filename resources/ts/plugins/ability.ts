import type { MongoAbility } from '@casl/ability'
import { createMongoAbility } from '@casl/ability'

export const ability = createMongoAbility([
  { action: 'view', subject: 'dashboard' },
  { action: 'manage', subject: 'all' },
  { action: 'view', subject: 'all' },
]) as MongoAbility
