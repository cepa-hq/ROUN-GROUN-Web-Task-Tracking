# Roun Groun — Multi-Agent Web Development Coordination

This repository is the shared coordination hub for AI agents and developers working on the Roun Groun website.

- **Agent instructions:** [AGENTS.md](AGENTS.md)
- **Coordination workflow:** [docs/agent-coordination/README.md](docs/agent-coordination/README.md)
- **Task templates:** [Task plan](docs/agent-coordination/TASK_PLAN_TEMPLATE.md) · [Completion report](docs/agent-coordination/COMPLETION_REPORT_TEMPLATE.md) · [Conflict report](docs/agent-coordination/CONFLICT_REPORT_TEMPLATE.md)
- **Staging site:** https://testarea.roungroun.com

## Repository boundary

This is a task-tracking and coordination repository. It is not confirmed to contain the website source code. Confirm the actual website source repository with the project owner before inspecting or editing website code.

## Mandatory pre-change rule

Before changing website source code, every agent must publish a visible plan in a GitHub Issue here. The plan must state why the change is needed, affected files/components, proposed approach, conflicts checked, tests and risks. Agents must inspect existing open tasks and stop to coordinate when scopes overlap.

GitHub Issues and branches are cooperative coordination mechanisms, not atomic locks. They cannot reveal another agent's unpushed local work. Agents must publish plans and task branches and communicate when overlap is discovered.

## Initial setup

The repository was empty when initialized. This coordination setup does not modify the website source or deploy to staging.
