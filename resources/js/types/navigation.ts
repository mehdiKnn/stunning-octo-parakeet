import type { InertiaLinkProps } from '@inertiajs/react';
import type { ComponentType, SVGProps } from 'react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type IconComponent = ComponentType<SVGProps<SVGSVGElement>>;

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: IconComponent | null;
    isActive?: boolean;
};
