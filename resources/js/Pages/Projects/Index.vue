<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import NavLink from '@/Components/NavLink.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SectionBorder from '@/Components/SectionBorder.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
    projects: Array,
    subscription: Object,
    plan: Object,
});

const confirmingProjectDeletion = ref(false);
const projectToDelete = ref(null);

const confirmDelete = (project) => {
    projectToDelete.value = project;
    confirmingProjectDeletion.value = true;
};

const deleteProject = () => {
    router.delete(route('projects.destroy', projectToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingProjectDeletion.value = false;
            projectToDelete.value = null;
        },
    });
};
</script>

<template>
    <AppLayout title="專案">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    專案列表
                </h2>
                <NavLink :href="route('projects.create')">
                    <PrimaryButton>
                        + 新增專案
                    </PrimaryButton>
                </NavLink>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- 訂閱方案資訊 -->
                <div v-if="subscription" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">目前方案</p>
                            <p class="text-lg font-semibold text-gray-800">
                                {{ plan?.name ?? '—' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500">專案用量</p>
                            <p class="text-lg font-semibold text-gray-800">
                                {{ projects.length }} / {{ plan?.max_projects ?? '∞' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 專案卡片列表 -->
                <div v-if="projects.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="project in projects"
                        :key="project.id"
                        class="bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200"
                    >
                        <div class="p-6 flex flex-col gap-3 h-full">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-lg font-semibold text-gray-800 break-words">
                                    {{ project.name }}
                                </h3>
                            </div>
                            <p v-if="project.description" class="text-sm text-gray-500 flex-1 line-clamp-3">
                                {{ project.description }}
                            </p>
                            <p v-else class="text-sm text-gray-400 italic flex-1">
                                尚無描述
                            </p>
                            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                <span class="text-xs text-gray-400">
                                    建立於 {{ new Date(project.created_at).toLocaleDateString('zh-TW') }}
                                </span>
                                <DangerButton
                                    class="!py-1 !px-3 text-xs"
                                    @click="confirmDelete(project)"
                                >
                                    刪除
                                </DangerButton>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 空狀態 -->
                <div v-else class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-12 text-center">
                        <svg class="mx-auto size-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-semibold text-gray-700">還沒有任何專案</h3>
                        <p class="mt-1 text-sm text-gray-500">建立第一個專案，開始使用看板功能。</p>
                        <div class="mt-6">
                            <NavLink :href="route('projects.create')">
                                <PrimaryButton>
                                    + 新增專案
                                </PrimaryButton>
                            </NavLink>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 刪除確認 Modal -->
        <ConfirmationModal :show="confirmingProjectDeletion" @close="confirmingProjectDeletion = false">
            <template #title>
                刪除專案
            </template>
            <template #content>
                確定要刪除「<strong>{{ projectToDelete?.name }}</strong>」嗎？此操作無法復原。
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingProjectDeletion = false">
                    取消
                </SecondaryButton>
                <DangerButton class="ms-3" @click="deleteProject">
                    確認刪除
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
