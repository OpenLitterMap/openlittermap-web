-- KEYS[1] = user set of unlocked slugs  {u:id}:ach
-- KEYS[2] = user xp string             {u:id}:xp
-- ARGV[1] = added xp (integer)
-- ARGV[2…n] = slugs to insert

local added = 0
for i = 2, #ARGV do
  if redis.call('SADD', KEYS[1], ARGV[i]) == 1 then
    added = 1
  end
end
if added == 1 then
  redis.call('INCRBY', KEYS[2], ARGV[1])
end
return true
