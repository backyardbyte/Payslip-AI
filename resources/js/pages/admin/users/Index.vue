<template>
    <Head title="User Management" />
    
    <AppLayout>
        <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
            <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">User Management</h1>
                <p class="text-muted-foreground">Manage system users and their access</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    v-if="permissions.canCreateUsers"
                    @click="showCreateModal = true"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Add User
                </button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="rounded-md border">
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search users..."
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="selectedRole"
                            class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="">All Roles</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="operator">Operator</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left p-2 font-medium text-xs">Name</th>
                                <th class="text-left p-2 font-medium text-xs">Email</th>
                                <th class="text-left p-2 font-medium text-xs">Role</th>
                                <th class="text-left p-2 font-medium text-xs">Status</th>
                                <th class="text-left p-2 font-medium text-xs">Last Login</th>
                                <th class="text-left p-2 font-medium text-xs">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in filteredUsers" :key="user.id" class="border-b">
                                <td class="p-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-full bg-primary/10 flex items-center justify-center">
                                            <span class="text-xs font-medium">{{ user.name.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <span class="font-medium text-sm">{{ user.name }}</span>
                                    </div>
                                </td>
                                <td class="p-2 text-muted-foreground text-sm">{{ user.email }}</td>
                                <td class="p-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-secondary text-secondary-foreground">
                                        {{ user.role?.display_name || 'No Role' }}
                                    </span>
                                </td>
                                <td class="p-2">
                                    <span 
                                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                        :class="user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                    >
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="p-2 text-muted-foreground text-sm">
                                    {{ user.last_login_at ? formatDate(user.last_login_at) : 'Never' }}
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center gap-1">
                                        <button
                                            v-if="permissions.canUpdateUsers"
                                            @click="editUser(user)"
                                            class="inline-flex items-center justify-center rounded-md h-7 w-7 text-muted-foreground hover:text-foreground hover:bg-muted"
                                        >
                                            <Edit class="h-3 w-3" />
                                        </button>
                                        <button
                                            v-if="permissions.canActivateUsers"
                                            @click="toggleUserStatus(user)"
                                            class="inline-flex items-center justify-center rounded-md h-7 w-7 text-muted-foreground hover:text-foreground hover:bg-muted"
                                        >
                                            <component :is="user.is_active ? UserX : UserCheck" class="h-3 w-3" />
                                        </button>
                                        <button
                                            v-if="permissions.canDeleteUsers && user.id !== $page.props.auth.user.id"
                                            @click="deleteUser(user)"
                                            class="inline-flex items-center justify-center rounded-md h-7 w-7 text-red-600 hover:text-red-700 hover:bg-red-50"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="filteredUsers.length === 0" class="text-center py-8 text-muted-foreground">
                    No users found matching your criteria.
                </div>
                </div>
            </div>
        </div>

        <!-- Create User Modal -->
        <Dialog v-model:open="showCreateModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Add New User</DialogTitle>
                    <DialogDescription>
                        Create a new user account with the specified details.
                    </DialogDescription>
                </DialogHeader>
                
                <form @submit.prevent="createUser" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="name">Full Name</Label>
                        <Input
                            id="name"
                            v-model="newUser.name"
                            type="text"
                            placeholder="Enter full name"
                            required
                        />
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="email">Email Address</Label>
                        <Input
                            id="email"
                            v-model="newUser.email"
                            type="email"
                            placeholder="Enter email address"
                            required
                        />
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="password">Password</Label>
                        <Input
                            id="password"
                            v-model="newUser.password"
                            type="password"
                            placeholder="Enter password"
                            required
                        />
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="role">Role</Label>
                        <select
                            id="role"
                            v-model="newUser.role"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            required
                        >
                            <option value="">Select a role</option>
                            <option value="user">User</option>
                            <option value="operator">Operator</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                            <option v-if="$page.props.auth.user.role?.name === 'super_admin'" value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="is_active"
                            v-model:checked="newUser.is_active"
                        />
                        <Label for="is_active">Active user account</Label>
                    </div>
                </form>
                
                <DialogFooter>
                    <Button variant="outline" @click="showCreateModal = false">
                        Cancel
                    </Button>
                    <Button @click="createUser" :disabled="isCreating">
                        <LoaderCircle v-if="isCreating" class="w-4 h-4 mr-2 animate-spin" />
                        Create User
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit User Modal -->
        <Dialog v-model:open="showEditModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit User</DialogTitle>
                    <DialogDescription>
                        Update user account details and settings.
                    </DialogDescription>
                </DialogHeader>
                
                <form @submit.prevent="updateUser" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="edit_name">Full Name</Label>
                        <Input
                            id="edit_name"
                            v-model="editingUser.name"
                            type="text"
                            placeholder="Enter full name"
                            required
                        />
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="edit_email">Email Address</Label>
                        <Input
                            id="edit_email"
                            v-model="editingUser.email"
                            type="email"
                            placeholder="Enter email address"
                            required
                        />
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="edit_role">Role</Label>
                        <select
                            id="edit_role"
                            v-model="editingUser.role"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            required
                        >
                            <option value="">Select a role</option>
                            <option value="user">User</option>
                            <option value="operator">Operator</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                            <option v-if="$page.props.auth.user.role?.name === 'super_admin'" value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="edit_is_active"
                            v-model:checked="editingUser.is_active"
                        />
                        <Label for="edit_is_active">Active user account</Label>
                    </div>
                </form>
                
                <DialogFooter>
                    <Button variant="outline" @click="showEditModal = false">
                        Cancel
                    </Button>
                    <Button @click="updateUser" :disabled="isUpdating">
                        <LoaderCircle v-if="isUpdating" class="w-4 h-4 mr-2 animate-spin" />
                        Update User
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import { Plus, Edit, Trash2, UserCheck, UserX, LoaderCircle, Search, Filter } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import AppLayout from '@/layouts/AppLayout.vue'

interface User {
    id: number
    name: string
    email: string
    is_active: boolean
    last_login_at: string | null
    role?: {
        id: number
        name: string
        display_name: string
    }
}

interface Props {
    users: User[]
    permissions: {
        canCreateUsers: boolean
        canUpdateUsers: boolean
        canDeleteUsers: boolean
        canActivateUsers: boolean
        canAssignRoles: boolean
    }
}

const props = defineProps<Props>()
const page = usePage()

const searchQuery = ref('')
const selectedRole = ref('')
const showCreateModal = ref(false)
const showEditModal = ref(false)
const isCreating = ref(false)
const isUpdating = ref(false)

const newUser = ref({
    name: '',
    email: '',
    password: '',
    role: '',
    is_active: true
})

const editingUser = ref({
    id: null as number | null,
    name: '',
    email: '',
    role: '',
    is_active: true
})

const filteredUsers = computed(() => {
    let filtered = props.users

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        filtered = filtered.filter(user => 
            user.name.toLowerCase().includes(query) ||
            user.email.toLowerCase().includes(query)
        )
    }

    if (selectedRole.value) {
        filtered = filtered.filter(user => user.role?.name === selectedRole.value)
    }

    return filtered
})

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const editUser = (user: User) => {
    editingUser.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role?.name || '',
        is_active: user.is_active
    }
    showEditModal.value = true
}

const toggleUserStatus = async (user: User) => {
    try {
        const response = await fetch(`/api/admin/users/${user.id}/toggle-status`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        });

        const data = await response.json();
        
        if (data.success) {
            // Update user status in the local data
            const userIndex = props.users.findIndex(u => u.id === user.id);
            if (userIndex !== -1) {
                props.users[userIndex].is_active = data.is_active;
            }
            console.log(data.message);
        } else {
            console.error('Failed to toggle user status:', data.message);
        }
    } catch (error) {
        console.error('Error toggling user status:', error);
    }
}

const deleteUser = async (user: User) => {
    if (!confirm(`Are you sure you want to delete ${user.name}? This action cannot be undone.`)) {
        return;
    }

    try {
        await router.delete(`/admin/users/${user.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                console.log('User deleted successfully');
            },
            onError: (errors) => {
                console.error('Error deleting user:', errors);
            }
        });
    } catch (error) {
        console.error('Error deleting user:', error);
    }
}

const createUser = async () => {
    if (isCreating.value) return
    
    isCreating.value = true
    
    try {
        await router.post('/admin/users', {
            name: newUser.value.name,
            email: newUser.value.email,
            password: newUser.value.password,
            role: newUser.value.role,
            is_active: newUser.value.is_active
        })
        
        // Reset form and close modal
        newUser.value = {
            name: '',
            email: '',
            password: '',
            role: '',
            is_active: true
        }
        showCreateModal.value = false
    } catch (error) {
        console.error('Error creating user:', error)
    } finally {
        isCreating.value = false
    }
}

const updateUser = async () => {
    if (isUpdating.value || !editingUser.value.id) return
    
    isUpdating.value = true
    
    try {
        await router.put(`/admin/users/${editingUser.value.id}`, {
            name: editingUser.value.name,
            email: editingUser.value.email,
            role: editingUser.value.role,
            is_active: editingUser.value.is_active
        })
        
        // Reset form and close modal
        editingUser.value = {
            id: null,
            name: '',
            email: '',
            role: '',
            is_active: true
        }
        showEditModal.value = false
    } catch (error) {
        console.error('Error updating user:', error)
    } finally {
        isUpdating.value = false
    }
}
</script>

 