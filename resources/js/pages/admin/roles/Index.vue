<template>
    <Head title="Roles & Permissions" />
    
    <AppLayout>
        <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
            <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Roles & Permissions</h1>
                <p class="text-muted-foreground">Manage system roles and their permissions</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="showCreateRoleModal = true"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Add Role
                </button>
            </div>
        </div>

        <!-- Roles Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="role in roles"
                :key="role.id"
                class="rounded-lg border bg-card p-6 shadow-sm"
            >
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold">{{ role.display_name }}</h3>
                        <p class="text-sm text-muted-foreground">{{ role.description || 'No description' }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            @click="editRole(role)"
                            class="inline-flex items-center justify-center rounded-md h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-muted"
                        >
                            <Edit class="h-4 w-4" />
                        </button>
                        <button
                            v-if="!isSystemRole(role.name)"
                            @click="deleteRole(role)"
                            class="inline-flex items-center justify-center rounded-md h-8 w-8 text-red-600 hover:text-red-700 hover:bg-red-50"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Permissions</span>
                        <span class="font-medium">{{ role.permissions?.length || 0 }}</span>
                    </div>
                    
                    <div class="space-y-2">
                        <div
                            v-for="category in getPermissionCategories(role.permissions)"
                            :key="category.name"
                            class="flex items-center justify-between text-xs"
                        >
                            <span class="text-muted-foreground capitalize">{{ category.name.replace('_', ' ') }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-1 bg-secondary text-secondary-foreground font-medium">
                                {{ category.count }}
                            </span>
                        </div>
                    </div>

                    <button
                        @click="viewRolePermissions(role)"
                        class="w-full mt-4 inline-flex items-center justify-center rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground"
                    >
                        <Shield class="mr-2 h-4 w-4" />
                        View Permissions
                    </button>
                </div>
            </div>
        </div>

        <!-- Permissions Overview -->
        <div class="rounded-lg border bg-card p-6 shadow-sm">
            <h2 class="text-lg font-semibold mb-4">All Permissions</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(categoryPermissions, category) in permissions"
                    :key="category"
                    class="space-y-2"
                >
                    <h3 class="font-medium capitalize">{{ category.replace('_', ' ') }}</h3>
                    <div class="space-y-1">
                        <div
                            v-for="permission in categoryPermissions"
                            :key="permission.id"
                            class="flex items-center justify-between text-sm p-2 rounded border"
                        >
                            <span class="text-muted-foreground">{{ permission.display_name }}</span>
                            <code class="text-xs bg-muted px-1 py-0.5 rounded">{{ permission.name }}</code>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Create Role Modal -->
        <Dialog v-model:open="showCreateRoleModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Add New Role</DialogTitle>
                    <DialogDescription>
                        Create a new role with the specified details and permissions.
                    </DialogDescription>
                </DialogHeader>
                
                <form @submit.prevent="createRole" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="name">Role Name</Label>
                        <Input
                            id="name"
                            v-model="newRole.name"
                            type="text"
                            placeholder="Enter role name (e.g., manager)"
                            required
                        />
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="display_name">Display Name</Label>
                        <Input
                            id="display_name"
                            v-model="newRole.display_name"
                            type="text"
                            placeholder="Enter display name (e.g., Manager)"
                            required
                        />
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="newRole.description"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Enter role description"
                        ></textarea>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="is_active"
                            v-model:checked="newRole.is_active"
                        />
                        <Label for="is_active">Active role</Label>
                    </div>
                </form>
                
                <DialogFooter>
                    <Button variant="outline" @click="showCreateRoleModal = false">
                        Cancel
                    </Button>
                    <Button @click="createRole" :disabled="isCreatingRole">
                        <LoaderCircle v-if="isCreatingRole" class="w-4 h-4 mr-2 animate-spin" />
                        Create Role
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- View Role Permissions Modal -->
        <Dialog v-model:open="showPermissionsModal">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ selectedRole?.display_name }} Permissions</DialogTitle>
                    <DialogDescription>
                        View and manage permissions for this role.
                    </DialogDescription>
                </DialogHeader>
                
                <div v-if="selectedRole" class="space-y-4 max-h-96 overflow-y-auto">
                    <div
                        v-for="(categoryPermissions, category) in groupedPermissions"
                        :key="category"
                        class="space-y-2"
                    >
                        <h3 class="font-medium capitalize text-sm">{{ String(category).replace('_', ' ') }}</h3>
                        <div class="grid gap-2">
                            <div
                                v-for="permission in categoryPermissions"
                                :key="permission.id"
                                class="flex items-center justify-between p-2 rounded border bg-muted/50"
                            >
                                <div class="flex-1">
                                    <p class="text-sm font-medium">{{ permission.display_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ permission.description || permission.name }}</p>
                                </div>
                                <div class="flex items-center">
                                    <Badge 
                                        :variant="hasPermission(permission.id) ? 'default' : 'secondary'"
                                        class="text-xs"
                                    >
                                        {{ hasPermission(permission.id) ? 'Granted' : 'Not Granted' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <DialogFooter>
                    <Button variant="outline" @click="showPermissionsModal = false">
                        Close
                    </Button>
                    <Button @click="editRolePermissions" v-if="selectedRole && !isSystemRole(selectedRole.name)">
                        Edit Permissions
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Role Modal -->
        <Dialog v-model:open="showEditRoleModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Role</DialogTitle>
                    <DialogDescription>
                        Update role details and settings.
                    </DialogDescription>
                </DialogHeader>
                
                <form @submit.prevent="updateRole" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="edit_name">Role Name</Label>
                        <Input
                            id="edit_name"
                            v-model="editingRole.name"
                            type="text"
                            placeholder="Enter role name (e.g., manager)"
                            required
                        />
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="edit_display_name">Display Name</Label>
                        <Input
                            id="edit_display_name"
                            v-model="editingRole.display_name"
                            type="text"
                            placeholder="Enter display name (e.g., Manager)"
                            required
                        />
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="edit_description">Description</Label>
                        <textarea
                            id="edit_description"
                            v-model="editingRole.description"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Enter role description"
                        ></textarea>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="edit_is_active"
                            v-model:checked="editingRole.is_active"
                        />
                        <Label for="edit_is_active">Active role</Label>
                    </div>
                </form>
                
                <DialogFooter>
                    <Button variant="outline" @click="showEditRoleModal = false">
                        Cancel
                    </Button>
                    <Button @click="updateRole" :disabled="isUpdatingRole">
                        <LoaderCircle v-if="isUpdatingRole" class="w-4 h-4 mr-2 animate-spin" />
                        Update Role
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Plus, Edit, Trash2, Shield, LoaderCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import AppLayout from '@/layouts/AppLayout.vue'

interface Permission {
    id: number
    name: string
    display_name: string
    description?: string
    category: string
}

interface Role {
    id: number
    name: string
    display_name: string
    description?: string
    is_active?: boolean
    permissions?: Permission[]
}

interface Props {
    roles: Role[]
    permissions: Record<string, Permission[]>
}

const props = defineProps<Props>()

const showCreateRoleModal = ref(false)
const showEditRoleModal = ref(false)
const showPermissionsModal = ref(false)
const isCreatingRole = ref(false)
const isUpdatingRole = ref(false)
const selectedRole = ref<Role | null>(null)

const newRole = ref({
    name: '',
    display_name: '',
    description: '',
    is_active: true
})

const editingRole = ref({
    id: null as number | null,
    name: '',
    display_name: '',
    description: '',
    is_active: true
})

const systemRoles = ['super_admin', 'admin', 'manager', 'operator', 'user']

const isSystemRole = (roleName: string) => {
    return systemRoles.includes(roleName)
}

const getPermissionCategories = (permissions: Permission[] = []) => {
    const categories: Record<string, number> = {}
    
    permissions.forEach(permission => {
        categories[permission.category] = (categories[permission.category] || 0) + 1
    })
    
    return Object.entries(categories).map(([name, count]) => ({ name, count }))
}

const editRole = (role: Role) => {
    editingRole.value = {
        id: role.id,
        name: role.name,
        display_name: role.display_name,
        description: role.description || '',
        is_active: role.is_active ?? true
    }
    showEditRoleModal.value = true
}

const deleteRole = (role: Role) => {
    // TODO: Implement delete role functionality
    if (confirm(`Are you sure you want to delete the role "${role.display_name}"?`)) {
        console.log('Delete role:', role)
    }
}

const viewRolePermissions = (role: Role) => {
    selectedRole.value = role
    showPermissionsModal.value = true
}

// Computed property for grouped permissions
const groupedPermissions = computed(() => {
    if (!selectedRole.value) return {}
    return props.permissions
})

// Check if selected role has a specific permission
const hasPermission = (permissionId: number) => {
    if (!selectedRole.value?.permissions) return false
    return selectedRole.value.permissions.some(p => p.id === permissionId)
}

const editRolePermissions = () => {
    // TODO: Implement edit role permissions functionality
    console.log('Edit permissions for role:', selectedRole.value)
    showPermissionsModal.value = false
}

const createRole = async () => {
    if (isCreatingRole.value) return
    
    isCreatingRole.value = true
    
    try {
        await router.post('/admin/roles', {
            name: newRole.value.name,
            display_name: newRole.value.display_name,
            description: newRole.value.description,
            is_active: newRole.value.is_active
        })
        
        // Reset form and close modal
        newRole.value = {
            name: '',
            display_name: '',
            description: '',
            is_active: true
        }
        showCreateRoleModal.value = false
    } catch (error) {
        console.error('Error creating role:', error)
    } finally {
        isCreatingRole.value = false
    }
}

const updateRole = async () => {
    if (isUpdatingRole.value || !editingRole.value.id) return
    
    isUpdatingRole.value = true
    
    try {
        await router.put(`/admin/roles/${editingRole.value.id}`, {
            name: editingRole.value.name,
            display_name: editingRole.value.display_name,
            description: editingRole.value.description,
            is_active: editingRole.value.is_active
        })
        
        // Reset form and close modal
        editingRole.value = {
            id: null,
            name: '',
            display_name: '',
            description: '',
            is_active: true
        }
        showEditRoleModal.value = false
    } catch (error) {
        console.error('Error updating role:', error)
    } finally {
        isUpdatingRole.value = false
    }
}
</script>

 