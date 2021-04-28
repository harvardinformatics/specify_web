select remarks
from agent a
where a.agenttype = 1
  and a.agentid in (select agentid from collector where agentid is not null)
  and a.agentid not in (select agentid from agentgeography where agentid is not null)
  and a.agentid not in (select agentid from agentcitation where agentid is not null)
  and a.agentid not in (select agentid from agentspecialty where agentid is not null)
  -- and a.agentid not in (select agentid from agentvariant where agentid is not null)
;

show tables;

select * from agentvariant;