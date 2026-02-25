# AGENTS.md - Coding Guidelines for AI Agents

## 🎯 Core Concept

[TO BE FILLED]

### Problem Solved

[TO BE FILLED]

### Solution

[TO BE FILLED]

---

## 🏗️ Architecture

### Overview

[TO BE FILLED]

### Main Components

[TO BE FILLED]

---

## 🚀 Typical Use Cases

[TO BE FILLED]

---

## 💡 Design Patterns Used

[TO BE FILLED]

---

## Project breakdown

[TO BE FILLED]

**IMPORTANT**: This section should evolve with the project. When a new feature is created, updated or removed, this section should too.

## 🧪 Testing

This bundle should be covered by unit, integration and functional tests.
The tests are located in the `tests/{Unit|Integration|Functional}` folder.
Unit tests can use mocks or stubs if needed.

---

## Remarks & Guidelines

### General

- NEVER commit or push the git repository.
- When unsure about something, you MUST ask the user for clarification. Same goes it the user request is unclear.
- When facing a problem that has an easy "hacky" solution, and a more robust but more difficult to implement one, always choose the robust one:
  - Easy hacky fixes become technical debt, and can lead to issues down the road
  - Robust solutions means the project will remain serious and well-built.
- ALWAYS write tests for the important components. Better safe than sorry!
- Do NOT write ANY type documentation unless explicitly asked.
- Once a feature is complete, update the @README.md and @AGENTS.md accordingly.
- The @README.md file should consist of a project overview for end-users, not a technical explanation of the project. It should include:
  - Table of contents
  - Quick start / Installation
  - Core features
  - Configuration reference
  - Usage
  - Development / Contribution guidelines

### Symfony Bundles

- Symfony bundles are meant to be re-used and integrated in other Symfony projects. When developing features, keep this in mind.  
- Architecture, naming, design, extensibility and easiness to install and use should be key priorities to consider when developing this project.

## 📚 References

- **Source code**: `/src`
- **Tests**: `/tests`
- **README**: User documentation
- **Symfony Docs**: https://symfony.com/doc/current/bundles.html

# FIRST READING OF THIS FILE

If you read this, it means the user did not yet fill this file.  
Ask the users the following questions :
- What problem is this bundle trying to solve?
- What solution have you considered to do so?
- What architecture have you thought about?
- What should be the name of the bundle?
- What are some typical usecases for this?

When answered, read this file again (@AGENTS.md) and fill the parts that contain "[TO BE FILLED]" content. Keep the file under 500 lines.  
This file will be read by every future developer working on this project.

Keep it simple, efficient and clear.  
Great documentation is easy to read.  
Do NOT overcomplicate things.  
Do NOT include examples for everything.
