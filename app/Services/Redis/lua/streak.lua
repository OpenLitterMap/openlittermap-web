-- KEYS[1] = uploads_today  (e.g. "{u:42}:uploads:2025-04-26")
-- KEYS[2] = uploads_yday   (e.g. "{u:42}:uploads:2025-04-25")
-- KEYS[3] = streak_key     (e.g. "{u:42}:streak")
-- ARGV[1] = TTL seconds to set on KEYS[1]                -- 60*60*24*35
-- Returns: integer = current streak length

-- 1. increment today's counter atomically
local today = redis.call('INCR', KEYS[1])

-- 2. refresh TTL so daily keys evaporate
redis.call('EXPIRE', KEYS[1], tonumber(ARGV[1]))

-- 3. fetch yesterday & current streak
local yesterday = tonumber(redis.call('GET', KEYS[2]) or '0')
local streak    = tonumber(redis.call('GET', KEYS[3]) or '0')

-- 4. update streak only on first upload of the day
if today == 1 then
  if yesterday > 0 then
    streak = streak + 1
  else
    streak = 1
  end
  redis.call('SET', KEYS[3], streak)
end

return streak
