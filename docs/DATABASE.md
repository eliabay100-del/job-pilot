# Database

PostgreSQL (with pgvector). Schema is built phase by phase via Laravel migrations; this file will hold the ERD as it grows.

```text
User
 ├── CandidateProfile
 │     ├── Education
 │     ├── Experience
 │     ├── Skills
 │     ├── Projects
 │     └── CVs
 ├── Applications
 └── Subscriptions

Company
 └── Jobs
       ├── Skills
       └── Applications

Job
 └── JobMatch
       └── Candidate

Subscription
 └── Payments
```
