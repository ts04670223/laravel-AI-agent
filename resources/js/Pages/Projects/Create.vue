<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormSection from '@/Components/FormSection.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ActionMessage from '@/Components/ActionMessage.vue';
import { Link } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
});

const submit = () => {
    form.post(route('projects.store'), {
        errorBag: 'createProject',
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="新增專案">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                新增專案
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <FormSection @submitted="submit">
                    <template #title>
                        專案資訊
                    </template>

                    <template #description>
                        為這個租戶新增一個看板專案。專案建立後，可在其中管理看板欄位與任務。
                    </template>

                    <template #form>
                        <!-- 專案名稱 -->
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="name" value="專案名稱" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                autofocus
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <!-- 描述 -->
                        <div class="col-span-6">
                            <InputLabel for="description" value="描述（選填）" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                placeholder="簡短描述這個專案的用途..."
                            />
                            <InputError :message="form.errors.description" class="mt-2" />
                        </div>
                    </template>

                    <template #actions>
                        <Link :href="route('projects.index')">
                            <SecondaryButton type="button">
                                取消
                            </SecondaryButton>
                        </Link>

                        <ActionMessage :on="form.recentlySuccessful" class="me-3">
                            已儲存。
                        </ActionMessage>

                        <PrimaryButton
                            class="ms-3"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            建立專案
                        </PrimaryButton>
                    </template>
                </FormSection>
            </div>
        </div>
    </AppLayout>
</template>
