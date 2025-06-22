# Payslip AI - Roles and Permissions Documentation

## Overview

This document outlines the complete role-based access control (RBAC) system implemented in Payslip AI. The system provides granular permission management with 5 predefined roles and 35 specific permissions across 6 categories.

## System Architecture

- **Roles**: Define user types with specific permission sets
- **Permissions**: Granular access controls for specific actions
- **Role-Permission Mapping**: Many-to-many relationship between roles and permissions
- **Direct User Permissions**: Override role permissions for specific users
- **Permission Caching**: 5-minute Redis cache for performance optimization

---

## 🔐 System Roles

### 1. Super Administrator (`super_admin`)
**Description**: Full system access with all permissions  
**Use Case**: System owner, technical administrator  
**Permission Count**: All 35 permissions  

### 2. Administrator (`admin`)
**Description**: System administrator with most permissions  
**Use Case**: Business administrator, system manager  
**Permission Count**: 25 permissions  

### 3. Manager (`manager`)
**Description**: Manager with oversight permissions  
**Use Case**: Department manager, supervisor  
**Permission Count**: 10 permissions  

### 4. Operator (`operator`)
**Description**: System operator with processing permissions  
**Use Case**: Data entry operator, processing staff  
**Permission Count**: 7 permissions  

### 5. Regular User (`user`)
**Description**: Standard user with basic permissions  
**Use Case**: End users, employees  
**Permission Count**: 5 permissions  

---

## 📋 Permission Categories

### 🗂️ Payslip Management (6 permissions)

| Permission | Code | Description | Super Admin | Admin | Manager | Operator | User |
|------------|------|-------------|:-----------:|:-----:|:-------:|:--------:|:----:|
| View Payslips | `payslip.view` | View payslip records | ✅ | ✅ | ✅ | ✅ | ✅ |
| Upload Payslips | `payslip.create` | Upload new payslip files | ✅ | ✅ | ❌ | ✅ | ✅ |
| Update Payslips | `payslip.update` | Edit payslip information | ✅ | ✅ | ❌ | ✅ | ✅ |
| Delete Payslips | `payslip.delete` | Delete payslip records | ✅ | ✅ | ❌ | ❌ | ✅ |
| View All Payslips | `payslip.view_all` | View payslips from all users | ✅ | ✅ | ✅ | ❌ | ❌ |
| Process Payslips | `payslip.process` | Manually trigger payslip processing | ✅ | ✅ | ✅ | ✅ | ❌ |

### 🏢 Koperasi Management (5 permissions)

| Permission | Code | Description | Super Admin | Admin | Manager | Operator | User |
|------------|------|-------------|:-----------:|:-----:|:-------:|:--------:|:----:|
| View Koperasi | `koperasi.view` | View koperasi information | ✅ | ✅ | ✅ | ✅ | ✅ |
| Create Koperasi | `koperasi.create` | Create new koperasi entries | ✅ | ✅ | ❌ | ❌ | ❌ |
| Update Koperasi | `koperasi.update` | Edit koperasi information | ✅ | ✅ | ❌ | ❌ | ❌ |
| Delete Koperasi | `koperasi.delete` | Delete koperasi entries | ✅ | ✅ | ❌ | ❌ | ❌ |
| Manage Koperasi Rules | `koperasi.manage_rules` | Modify koperasi eligibility rules | ✅ | ✅ | ❌ | ❌ | ❌ |

### 👥 User Management (6 permissions)

| Permission | Code | Description | Super Admin | Admin | Manager | Operator | User |
|------------|------|-------------|:-----------:|:-----:|:-------:|:--------:|:----:|
| View Users | `user.view` | View user accounts | ✅ | ✅ | ✅ | ❌ | ❌ |
| Create Users | `user.create` | Create new user accounts | ✅ | ✅ | ❌ | ❌ | ❌ |
| Update Users | `user.update` | Edit user information | ✅ | ✅ | ❌ | ❌ | ❌ |
| Delete Users | `user.delete` | Delete user accounts | ✅ | ❌ | ❌ | ❌ | ❌ |
| Activate/Deactivate Users | `user.activate` | Enable or disable user accounts | ✅ | ✅ | ❌ | ❌ | ❌ |
| Assign User Roles | `user.assign_roles` | Assign roles to users | ✅ | ✅ | ❌ | ❌ | ❌ |

### 🔧 Role & Permission Management (2 permissions)

| Permission | Code | Description | Super Admin | Admin | Manager | Operator | User |
|------------|------|-------------|:-----------:|:-----:|:-------:|:--------:|:----:|
| View Roles | `role.view` | View system roles | ✅ | ❌ | ❌ | ❌ | ❌ |
| Create Roles | `role.create` | Create new roles | ✅ | ❌ | ❌ | ❌ | ❌ |
| Update Roles | `role.update` | Edit role information | ✅ | ❌ | ❌ | ❌ | ❌ |
| Delete Roles | `role.delete` | Delete roles | ✅ | ❌ | ❌ | ❌ | ❌ |
| View Permissions | `permission.view` | View system permissions | ✅ | ❌ | ❌ | ❌ | ❌ |
| Assign Permissions | `permission.assign` | Assign permissions to roles | ✅ | ❌ | ❌ | ❌ | ❌ |

### ⚙️ System Management (7 permissions)

| Permission | Code | Description | Super Admin | Admin | Manager | Operator | User |
|------------|------|-------------|:-----------:|:-----:|:-------:|:--------:|:----:|
| View System Health | `system.view_health` | View system health information | ✅ | ✅ | ✅ | ✅ | ❌ |
| View Statistics | `system.view_statistics` | View system statistics | ✅ | ✅ | ✅ | ❌ | ❌ |
| Clear Cache | `system.clear_cache` | Clear system cache | ✅ | ✅ | ❌ | ❌ | ❌ |
| Optimize Database | `system.optimize_database` | Run database optimization | ✅ | ❌ | ❌ | ❌ | ❌ |
| System Cleanup | `system.cleanup` | Run system cleanup operations | ✅ | ✅ | ❌ | ❌ | ❌ |
| Clear Logs | `system.clear_logs` | Clear system logs | ✅ | ✅ | ❌ | ❌ | ❌ |
| Manage Settings | `system.manage_settings` | Manage system settings | ✅ | ❌ | ❌ | ❌ | ❌ |

### 📊 Analytics & Reports (3 permissions)

| Permission | Code | Description | Super Admin | Admin | Manager | Operator | User |
|------------|------|-------------|:-----------:|:-----:|:-------:|:--------:|:----:|
| View Analytics | `analytics.view` | View analytics dashboard | ✅ | ✅ | ✅ | ❌ | ❌ |
| Export Analytics | `analytics.export` | Export analytics data | ✅ | ✅ | ❌ | ❌ | ❌ |
| Generate Reports | `report.generate` | Generate system reports | ✅ | ✅ | ✅ | ❌ | ❌ |

### 🔄 Queue Management (3 permissions)

| Permission | Code | Description | Super Admin | Admin | Manager | Operator | User |
|------------|------|-------------|:-----------:|:-----:|:-------:|:--------:|:----:|
| View Queue | `queue.view` | View processing queue | ✅ | ✅ | ✅ | ✅ | ❌ |
| Manage Queue | `queue.manage` | Manage processing queue | ✅ | ✅ | ❌ | ✅ | ❌ |
| Clear Queue | `queue.clear` | Clear processing queue | ✅ | ✅ | ❌ | ❌ | ❌ |

---

## 🔑 System Credentials

### Default User Accounts

| Role | Email | Password | Status |
|------|-------|----------|--------|
| **Super Admin** | `admin@payslip-ai.local` | `PayslipAI@2025!` | Active |
| **Admin** | `manager@payslip-ai.local` | `Manager@2025!` | Active |
| **Manager** | `operations@payslip-ai.local` | `Operations@2025!` | Active |
| **Operator** | `operator@payslip-ai.local` | `Operator@2025!` | Active |
| **User** | `demo@payslip-ai.local` | `Demo@2025!` | Active |
| **User** | `test@example.com` | `password` | Active |

> ⚠️ **Security Warning**: Change these default passwords immediately in production environments!

---

## 💻 Implementation Examples

### Route Protection

```php
// Protect with specific permission
Route::middleware(['auth', 'permission:payslip.view_all'])->group(function () {
    Route::get('/admin/payslips', [PayslipController::class, 'adminIndex']);
});

// Protect with role (multiple roles allowed)
Route::middleware(['auth', 'role:admin,super_admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});
```

### Controller Protection

```php
public function __construct()
{
    // Apply permission middleware to specific methods
    $this->middleware('permission:user.view')->only(['index', 'show']);
    $this->middleware('permission:user.create')->only(['create', 'store']);
    $this->middleware('permission:user.update')->only(['edit', 'update']);
    $this->middleware('permission:user.delete')->only(['destroy']);
}
```

### Permission Checking in Code

```php
// Check single permission
if (auth()->user()->hasPermission('payslip.delete')) {
    // User can delete payslips
}

// Check multiple permissions (ANY)
if (auth()->user()->hasAnyPermission(['user.create', 'user.update'])) {
    // User can create OR update users
}

// Check multiple permissions (ALL)
if (auth()->user()->hasAllPermissions(['payslip.view', 'payslip.create'])) {
    // User can both view AND create payslips
}

// Check role
if (auth()->user()->hasRole('admin')) {
    // User is an admin
}
```

### User Management

```php
// Assign role to user
$user->assignRole('admin');

// Give direct permission (overrides role)
$user->givePermission('payslip.view_all');

// Revoke direct permission
$user->revokePermission('payslip.view_all');

// Get all user permissions (role + direct)
$permissions = $user->getAllPermissions();
```

---

## 🔒 Security Features

### Account Protection
- Cannot delete or deactivate the last Super Administrator
- Account status tracking (active/inactive)
- Last login timestamp tracking

### Permission Caching
- 5-minute Redis cache per user per permission
- Automatic cache invalidation on role/permission changes
- User-specific cache keys for security

### Permission Inheritance
- Users inherit permissions from their assigned role
- Direct user permissions can override role permissions
- Granted permissions take precedence over denied permissions

---

## 📈 Permission Summary by Role

| Role | Total Permissions | Payslip | Koperasi | User | Role | System | Analytics | Queue |
|------|:-----------------:|:-------:|:--------:|:----:|:----:|:------:|:---------:|:-----:|
| **Super Admin** | 35 | 6/6 | 5/5 | 6/6 | 6/6 | 7/7 | 3/3 | 3/3 |
| **Admin** | 25 | 6/6 | 5/5 | 5/6 | 0/6 | 5/7 | 2/3 | 3/3 |
| **Manager** | 10 | 3/6 | 1/5 | 1/6 | 0/6 | 2/7 | 2/3 | 1/3 |
| **Operator** | 7 | 4/6 | 1/5 | 0/6 | 0/6 | 1/7 | 0/3 | 2/3 |
| **User** | 5 | 4/6 | 1/5 | 0/6 | 0/6 | 0/7 | 0/3 | 0/3 |

---

## 🚀 Getting Started

1. **Login with different roles** to test permission differences
2. **Access admin panel** at `/admin/users` (requires admin role)
3. **Use middleware** to protect routes and controllers
4. **Check permissions** in your application logic
5. **Customize roles** and permissions as needed

---

## 📝 Notes

- All permissions are stored in the database and can be modified
- Role-permission relationships are flexible and can be updated
- The system supports both role-based and direct user permissions
- Permission caching improves performance for frequent checks
- Audit logging can be added for compliance requirements

---

*Last Updated: January 2025*  
*Version: 1.0* 