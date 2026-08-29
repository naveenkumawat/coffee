# Coffee Architecture

## ZYLM Patterns Identified

- Module-first folder grouping across repositories, services, transfers, and parsers.
- Class and interface pairs grouped together inside the same module folder.
- Thin HTTP controllers and dedicated Form Requests.
- Shared abstract foundations such as abstract request, model, repository, parser, and transfer classes.
- Dedicated providers for each architectural binding concern.
- Parser classes that translate request or model data into transfer objects.
- Transfer objects used as the structured handoff between layers.
- Role-based controllers, routes, and views, with shared business logic living outside role folders.
- Events and listeners used for side effects instead of pushing those concerns into controllers.

## How Coffee Now Follows Them

- Repository interfaces and implementations are grouped by module in `app/Repositories/Menu`.
- Service interfaces and implementations are grouped by module in `app/Services/Menu` and `app/Services/Auth`.
- Transfer interfaces and implementations are grouped by module in `app/Transfers/Menu`.
- Parser interfaces and implementations are grouped by module in `app/Parsers/Menu`.
- Requests are grouped by module in `app/Http/Requests/MenuCategory` and `app/Http/Requests/MenuItem`, matching ZYLM's request organization more closely than role-grouped request folders.
- Shared abstract foundations now exist in `app/Models/AbstractModel`, `app/Http/Requests/AbstractRequest`, `app/Repositories/AbstractRepository`, `app/Parsers/AbstractParser`, and `app/Transfers/AbstractTransfer`.
- Separate providers now register repositories, services, parsers, and transfers.

## Module Grouping Convention

Related contracts and implementations must live together by business concern, not in flat global contract folders.

Example:

```text
app/
├── Http/
│   └── Requests/
│       ├── MenuCategory/
│       │   ├── MenuCategoryCreateRequest.php
│       │   └── MenuCategoryUpdateRequest.php
│       └── MenuItem/
│           ├── MenuItemCreateRequest.php
│           └── MenuItemUpdateRequest.php
├── Repositories/
│   └── Menu/
│       ├── MenuCategoryRepositoryInterface.php
│       ├── MenuCategoryRepository.php
│       ├── MenuItemRepositoryInterface.php
│       └── MenuItemRepository.php
├── Services/
│   └── Menu/
│       ├── MenuCatalogServiceInterface.php
│       ├── MenuCatalogService.php
│       ├── MenuCategoryServiceInterface.php
│       ├── MenuCategoryService.php
│       ├── MenuItemServiceInterface.php
│       └── MenuItemService.php
├── Transfers/
│   └── Menu/
│       ├── MenuCategoryTransferInterface.php
│       ├── MenuCategoryTransfer.php
│       ├── MenuItemTransferInterface.php
│       └── MenuItemTransfer.php
└── Parsers/
    └── Menu/
        ├── MenuCategoryParserInterface.php
        ├── MenuCategoryParser.php
        ├── MenuItemParserInterface.php
        └── MenuItemParser.php
```

Future modules such as `Ingredient`, `Inventory`, `Recipe`, `Order`, `Customer`, and `Payment` should follow the same grouping.

## Role-Specific vs Shared Responsibility

- Role-specific:
  - controllers
  - routes
  - views
  - route middleware application
  - panel entry and response orchestration
- Shared:
  - repositories
  - services
  - transfers
  - parsers
  - events and listeners
  - domain exceptions

Administrator and Barista may call the same shared business layer, but they should not receive duplicated role-specific business services if the underlying rule set is the same.

## Internal Role Naming Convention

- ZYLM's canonical internal management convention is `Administrator`, not `Admin`.
- Coffee must keep role-specific PHP namespaces, controller folders, Blade folders, and route files under the `Administrator` convention.
- Do not create a parallel `Admin` namespace or Blade tree beside the existing `Administrator` structure.
- The `admin` auth guard name is intentional and may remain different from the folder name because it is an authentication identifier, not an architectural namespace.
- The internal URL prefix also intentionally remains `/administrator`.

## Request, Parser, Transfer, Service Flow

The default write flow should be:

```text
Controller
  -> Form Request
  -> Parser
  -> Transfer
  -> Service
  -> Repository
  -> Model / Database
```

For current menu writes:

- the controller receives a validated request
- the parser converts the validated array into a transfer object
- the service owns the transaction and business orchestration
- the repository performs the persistence operation
- events/listeners or the service handle cache side effects

## Repository and Service Boundaries

- Repositories own reusable query composition, option lists, persistence helpers, and delete/write operations shared by services.
- Services own transactions, business workflows, and side effects that matter to application correctness.
- Controllers should not execute direct persistence or repeated query lookups when those belong in repositories or services.
- Models should keep casts, relationships, and scopes, not workflow logic.

## Dependency Injection and Bindings

- `RepositoryServiceProvider` binds repository contracts.
- `DomainServiceProvider` binds business service contracts.
- `TransferServiceProvider` binds transfer contracts.
- `ParserServiceProvider` binds parser contracts.
- Controllers, listeners, and dependent services should type-hint contracts rather than concrete implementations wherever the contract exists.

## Current Coffee Foundations Applied

- Menu category and menu item writes now pass through parser and transfer layers.
- Menu request classes are organized by module, not by role.
- Current menu controllers are thinner and do not directly delete records.
- Menu form option lookups remain centralized in repositories.
- Shared internal UI remains separate from the customer storefront.
- Existing event/listener cache invalidation remains intact.

## Naming Conventions

- Interface names end with `Interface`.
- Requests use `CreateRequest` and `UpdateRequest` naming by module.
- Transfers use `<Entity>Transfer` and `<Entity>TransferInterface`.
- Parsers use `<Entity>Parser` and `<Entity>ParserInterface`.
- Repositories use `<Entity>Repository` and `<Entity>RepositoryInterface`.
- Services use `<Entity>Service` and `<Entity>ServiceInterface`.

## Remaining Intentional Differences from ZYLM

- Coffee does not reproduce ZYLM's global root interface and factory graph because the current Laravel container can resolve the same seams more simply.
- Coffee has not added empty `Factories`, `Integrations`, `Tools`, `Jobs`, or domain-specific exception trees solely for parity; those should be added when a real shared use case arrives.
- Coffee keeps Laravel-native model factories and provider registration rather than wrapping all construction in a custom root object.

## Rules for Future Agents and Developers

- Do not add new business modules directly through controllers or large models.
- Do not create flat global `Interfaces/` folders.
- Group related classes and interfaces by module.
- Add Form Requests for write actions.
- Add parser and transfer classes when a module introduces a business-layer boundary.
- Put repeated queries and option sourcing in repositories.
- Put transactions and workflows in services.
- Add new providers only when a new architectural layer needs bindings.
- Keep public storefront behavior separate from internal panel architecture.
