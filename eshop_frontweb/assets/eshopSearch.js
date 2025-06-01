document.addEventListener('DOMContentLoaded', function() {
    const searchButton = document.querySelector('.header_right .search__button');
    const mobileSearchOverlay = document.getElementById('mobile-search-overlay');
    const closeSearchButton = document.querySelector('.close-search');
    const mobileSearchInput = document.querySelector('.mobile-search__input');
    const mobileSearchButton = document.querySelector('.mobile-search__button');
    const mobileSearchResults = document.getElementById('mobile-search-results');
    const desktopSearchInput = document.querySelector('.header_right .search__input');
    const isMobile = window.innerWidth <= 768;

    // 打开搜索弹窗
    searchButton.addEventListener('click', function(e) {
        e.preventDefault();
        if (isMobile) {
            // 移动版：显示搜索弹窗
            mobileSearchOverlay.style.display = 'block';
            mobileSearchInput.focus();
        } else {
            performSearch(); // 桌面版：直接执行搜索
        }
    });

    // 关闭搜索弹窗
    closeSearchButton.addEventListener('click', function() {
        mobileSearchOverlay.style.display = 'none';
        mobileSearchInput.value = '';
        mobileSearchResults.innerHTML = '';
    });

    // 点击遮罩层关闭弹窗
    mobileSearchOverlay.addEventListener('click', function(e) {
        if (e.target === mobileSearchOverlay) {
            mobileSearchOverlay.style.display = 'none';
            mobileSearchInput.value = '';
            mobileSearchResults.innerHTML = '';
        }
    });

    // 处理搜索
    function performSearch() {
        const searchTerm = mobileSearchInput.value.trim();
        const spinner = document.getElementById('loadingSpinner'); // 获取 spinner
        if (!isMobile) {
            console.log('Performing desktop search');
            const query = desktopSearchInput.value.trim();
            if (query) {
                spinner.style.display = 'block';
        
                // 等待下一帧 + 延迟 50ms 才跳转，让 spinner 有时间渲染
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        window.location.href = `/search/results?query=${encodeURIComponent(query)}`;
                    }, 50);
                });
            }
        }
    
        if (!searchTerm) {
            mobileSearchResults.innerHTML = '<p class="no-results">请输入搜索关键词</p>';
            return;
        }
    
        spinner.style.display = 'block'; // 显示加载动画
    
        fetch('{{ path("search") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ query: searchTerm })
        })
        .then(response => response.json())
        .then(data => {
            spinner.style.display = 'none'; // 隐藏加载动画
            mobileSearchResults.innerHTML = '';
    
            if (data.error) {
                mobileSearchResults.innerHTML = `<p class="no-results">${data.error}</p>`;
                return;
            }
            if (data.results && data.results.length > 0) {
                window.location.href = '{{ path("search_results") }}';
            } else {
                mobileSearchResults.innerHTML = '<p class="no-results">未找到相关产品</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            spinner.style.display = 'none'; // 出错也要隐藏
            mobileSearchResults.innerHTML = '<p class="error">搜索出错，请稍后重试</p>';
        });
    }

    // 点击搜索按钮
    mobileSearchButton.addEventListener('click', performSearch);

    // 按回车键搜索
    mobileSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // 阻止表单默认提交
            performSearch();
        }
    });

    // 电脑版回车键搜索
    desktopSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !isMobile) {
            e.preventDefault();
            const query = this.value.trim();
            if (query) {
                window.location.href = `/search/results?query=${encodeURIComponent(query)}`;
            }
        }
    });

    // 监听窗口大小变化
    window.addEventListener('resize', function() {
        const newIsMobile = window.innerWidth <= 768;
        if (newIsMobile !== isMobile) {
            if (!newIsMobile) {
                // 如果从移动端切换到桌面端，关闭搜索弹窗
                mobileSearchOverlay.style.display = 'none';
                mobileSearchInput.value = '';
                mobileSearchResults.innerHTML = '';
            }
        }
    });
});