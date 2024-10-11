import { defineConfig } from 'vitepress'
import { getNavBar } from "./nav";
import { getSideBarGuide, getSideBarExtend } from "./sidebar";


// https://vitepress.dev/reference/site-config
export default defineConfig({
    title: "PTAdmin/Easy",
    description: "PTAdmin/Easy, PTAdmin, CURD操作",
    lang: 'zh-CN',
    base: '/',
    head: [['link', { rel: 'icon', href: '/favicon.png' }]],
    ignoreDeadLinks: true,
    themeConfig: {
        logo: '/favicon.png',
        nav: getNavBar(),
        sidebar: {
            '/guide': getSideBarGuide(),
            '/extend': getSideBarExtend(),
        },
        socialLinks: [
            { icon: 'github', link: 'https://github.com/pangtou-com/ptadmin-easy' },
            // { icon: {svg: `<img src="https://gitee.com/static/images/logo-black.svg" style="height: 20px" alt="gitee">`},
            //     link: 'https://gitee.com/ptadmin/ptadmin-easy'
            // },
        ],
        footer: {
            message: 'Released under the MIT License.',
            copyright: 'Copyright © 2022-present <a href="https://www.pangtou.com">PTAdmin</a>'
        },
        outline: {
            label: '目录',
        },
        docFooter: {
            prev: '上一页',
            next: '下一页',
        },
        lastUpdatedText: '上次更新',
        search: {
            provider: 'local',
        },
    }
})
