# kiro-sdd-test

A sandbox repository for evaluating and demonstrating [Kiro](https://kiro.dev)'s spec-driven development (SDD) workflow.

---

## Getting Started

### Prerequisites

- [Kiro IDE](https://kiro.dev) installed
- Git

### 1. Clone the repository

```bash
git clone https://github.com/brokerUA/kiro-sdd-test.git
cd kiro-sdd-test
```

### 2. Open in Kiro

Open the cloned folder in Kiro IDE:

```bash
kiro .
```

Or use **File → Open Folder** from within Kiro.

---

## Development Workflow

This project uses Kiro's **spec-driven development** (SDD) methodology. All features and bugfixes are built through structured specs before any code is written.

### Creating a new spec

1. Open the Kiro chat panel
2. Describe what you want to build or fix (e.g. "Add user authentication" or "Fix crash when quantity is zero")
3. Kiro will guide you through:
   - **Requirements** — what the feature/fix must do
   - **Design** — how it will be implemented
   - **Tasks** — a step-by-step implementation plan
4. Spec files are saved to `.kiro/specs/{feature-name}/`

### Running tasks from a spec

Once a spec is ready, tell Kiro to execute it:

```
Run all tasks for <feature-name>
```

Or target a specific task:

```
Execute task 2 for <feature-name>
```

---

## Project Structure

```
kiro-sdd-test/
├── .git/               # Git version control
├── .kiro/
│   ├── specs/          # Spec files per feature (requirements, design, tasks)
│   └── steering/       # Always-included AI guidance for Kiro
├── .vscode/            # Editor settings
└── README.md           # This file
```

---

## Steering Files

Steering files in `.kiro/steering/` provide persistent context to Kiro across all sessions:

| File | Purpose |
|------|---------|
| `product.md` | What this project is and its goals |
| `structure.md` | Directory layout and conventions |
| `tech.md` | Tech stack, commands, and tooling |

Update these files as the project evolves so Kiro always has accurate context.

---

## Contributing

1. Create a spec for your change (see above)
2. Let Kiro implement the tasks
3. Review the changes
4. Commit and push

```bash
git add .
git commit -m "your message"
git push
```
