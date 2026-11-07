<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

import Heading from '@/components/Heading.vue';

import { Button } from '@/components/ui/button';

import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';

import Form, {
    type PackageValues,
    type PackageImage,
} from './Form.vue';

import {
    index,
    update,
} from '@/routes/admin/packages';


const props = defineProps<{
    package: PackageValues;

    images: PackageImage[];

    pickupLocations: {
        id: number;
        name: string;
    }[];

    destinationOptions: string[];
}>();


defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Packages',
                href: index(),
            },
            {
                title: 'Edit',
            },
        ],
    },
});
</script>


<template>

    <Head title="Edit Package" />

    <div class="px-4 py-6">

        <div class="max-w-6xl">

            <Button
                as-child
                variant="ghost"
                size="sm"
                class="mb-4 -ml-2"
            >

                <Link :href="index()">

                    <ArrowLeft
                        class="mr-2 h-4 w-4"
                    />

                    Back to packages

                </Link>

            </Button>


            <Card>

                <CardHeader>

                    <Heading
                        title="Edit Package"
                        :description="
                            `Editing ${props.package.package_name}`
                        "
                    />

                </CardHeader>


                <CardContent>

                    <Form
                        :package="props.package"
                        :images="props.images"
                        :pickup-locations="
                            pickupLocations
                        "
                        :destination-options="
                            destinationOptions
                        "
                        :submit-action="
                            update(props.package.id)
                        "
                        :cancel-href="
                            index().url
                        "
                        submit-label="Save Changes"
                    />

                </CardContent>

            </Card>

        </div>

    </div>

</template>