/**
 * 指引左侧菜单
 */
export function getSideBarGuide() {
    return [
        { text: 'PTAdmin\/Easy?', link: '/guide/index.md' },
        { text: '安装', link: '/guide/install.md' },
        {
            text: 'API',
            collapsible: true,
            items: [
                { text: 'Schema 生命周期', link: '/guide/schema-lifecycle.md' },
                { text: '关联使用', link: '/guide/relation.md' },
            ],
        },
        { text: '待办事项', link: '/guide/todo.md' },
    ]
}

/**
 * 扩展模块左侧菜单
 */
export function getSideBarExtend() {
    return [
        { text: '介绍', link: '/extend/index.md' },
        {
            text: '模型扩展',
            collapsible: true,
            items: [
                { text: '模型字段扩展', link: '/extend/mod.md' },
                { text: '模型事件处理', link: '/extend/field.md' },
            ],
        },
        {
            text: '组件扩展',
            collapsible: true,
            items: [
                { text: '自定义组件', link: '/extend/mod.md' },
                { text: '组件规则', link: '/extend/field.md' },
            ],
        },
    ]
}
