-- metrics.lua - ONE script per batch
-- ARGV[1] = JSON payload

local payload = cjson.decode(ARGV[1])

--------------------------------------------------------------------
-- 1. plain user stats
--------------------------------------------------------------------
if payload.uploads and payload.uploads > 0 then
 redis.call('HINCRBY', payload.statsKey, 'uploads', payload.uploads)
end
if payload.xp and payload.xp > 0 then
 redis.call('HINCRBYFLOAT', payload.statsKey, 'xp', tostring(payload.xp))
end

--------------------------------------------------------------------
-- 2. dimension hashes (user + global)
--------------------------------------------------------------------
if payload.updates then
 for _, upd in ipairs(payload.updates) do
   -- {key, field, amount}
   redis.call('HINCRBY', upd[1], upd[2], upd[3])
 end
end

--------------------------------------------------------------------
-- 3. time-series ({g}:YYYY-MM:t)
--------------------------------------------------------------------
if payload.timeSeries then
 for _, ts in ipairs(payload.timeSeries) do
   local k, f, a = ts[1], ts[2], ts[3]
   if f == 'xp' then
     redis.call('HINCRBYFLOAT', k, f, tostring(a))
   else
     redis.call('HINCRBY', k, f, a)
   end
 end
end

--------------------------------------------------------------------
-- 4. geo-scoped counts (c:…|s:…|ci:…)
--------------------------------------------------------------------
if payload.geo then
 for _, g in ipairs(payload.geo) do
   local k, f, a = g[1], g[2], g[3]
   redis.call('HINCRBY', k, f, a)
   redis.call('PEXPIRE', k, payload.ttl)
 end
end

--------------------------------------------------------------------
-- 5. streak bitmap - all dayIdx values already ASC-sorted
--------------------------------------------------------------------
if payload.dayIdxs and #payload.dayIdxs > 0 then
 local bitmap = payload.bitmapKey
 local streak = tonumber(redis.call('HGET', payload.statsKey, 'streak') or '0')

 for _, idx in ipairs(payload.dayIdxs) do
   local bits = redis.call('BITFIELD', bitmap,
                           'GET', 'u1', tostring(idx-1),
                           'SET', 'u1', tostring(idx), '1')
   local hadYesterday = bits[1] == 1
   if hadYesterday then
     streak = streak + 1
   else
     streak = 1
   end
 end

 redis.call('HSET', payload.statsKey, 'streak', tostring(streak))
end

return 'OK'
