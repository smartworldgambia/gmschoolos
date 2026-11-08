# Scaling Strategy

## Principle

Launch small and economical. Instrument early. Scale when measurements justify cost. Never equate “architected for growth” with “validated at 2M concurrent users.”

## Stages

### Stage 1 — Development and validation

- Local XAMPP / small managed services
- Synthetic seed data
- Automated tests on significant changes
- Reproducible performance scripts (later phases)

### Stage 2 — Initial production

- Managed app hosting (evaluate Laravel Cloud against limits and pricing)
- Managed MySQL with backups
- Shared Redis when needed
- Managed queue workers
- Object storage + CDN for public assets
- Spending alerts and a tested restore

### Stage 3 — Growing adoption

- Query/index tuning, public-page caching
- Read replicas if read load warrants
- Async heavy reports
- Tenant fairness (rate limits, job quotas)
- Regular load and failure tests

### Stage 4 — High-volume (AWS or equivalent)

- IaC, ALB, ECS/Fargate (or justified compute—not K8s by default)
- Independent worker pools, RDS/Aurora, ElastiCache, SQS, S3, CloudWatch
- Connection pooling and autoscaling with hard budget caps

### Stage 5 — Validated extreme scale

- Partition/shard or dedicated DB clusters for noisy tenants only after evidence
- Regional strategies when business/RPO justify them
- Distributed load tests and capacity certification per profile

## Autoscaling signals

Application: CPU, memory, RPS/instance, latency, error rate.  
Workers: queue depth, oldest job age, arrival rate.  
Database: connection budget must constrain app scale-out.

Prefer faster scale-out, conservative scale-in, scheduled capacity for exam/registration windows.

## Graceful degradation order

1. Auth  
2. Core student/staff records  
3. Attendance and exam submissions  
4. Fee transactions and payment callbacks  
5. Core LMS  
6. Notifications and bulk reports  
7. Analytics and expensive dashboards  

Never silently drop grades, payments, exam submissions, or attendance. Use durable queues and idempotency where required.

## Traffic profiles (to measure)

| Profile | Intent |
|---------|--------|
| A Ordinary school day | Attendance, timetables, normal LMS |
| B Peak education | Results day, enrolment, concurrent exams |
| C Extreme burst | Surges, bots, retries, announcements |

Report concurrent sessions and request-generating users separately.
