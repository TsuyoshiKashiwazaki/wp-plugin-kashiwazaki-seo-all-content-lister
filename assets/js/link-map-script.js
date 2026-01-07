/**
 * Kashiwazaki SEO All Content Lister - Link Map Script
 */

(function() {
    'use strict';

    var graphData = null;
    var simulation = null;
    var svg = null;
    var g = null;
    var centerPostId = null;
    var width, height;

    function init() {
        console.log('Link Map init started');
        console.log('Config:', kashiwazakiLinkMap);

        if (typeof kashiwazakiLinkMap === 'undefined') {
            showError('設定が読み込まれていません');
            return;
        }

        loadData();
    }

    function loadData() {
        console.log('Loading data from:', kashiwazakiLinkMap.ajaxurl);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', kashiwazakiLinkMap.ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                var loading = document.getElementById('link-map-loading');
                if (loading) loading.style.display = 'none';

                console.log('Response status:', xhr.status);
                console.log('Response text:', xhr.responseText.substring(0, 500));

                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            console.log('Data loaded successfully. Nodes:', response.data.nodes.length, 'Links:', response.data.links.length);
                            graphData = response.data;
                            populateSelect();
                            initGraph();
                        } else {
                            showError('データの取得に失敗しました: ' + (response.data || ''));
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                        showError('データの解析に失敗しました: ' + e.message);
                    }
                } else {
                    showError('通信エラーが発生しました (Status: ' + xhr.status + ')');
                }
            }
        };
        xhr.send('action=kashiwazaki_get_link_map_data&nonce=' + kashiwazakiLinkMap.nonce);
    }

    function showError(message) {
        var container = document.getElementById('link-map-container');
        container.innerHTML = '<div style="padding: 20px; color: #d63638;">' + message + '</div>';
    }

    function populateSelect() {
        var select = document.getElementById('center-post-select');
        if (!select || !graphData) return;

        // ノードをタイトルでソート
        var sortedNodes = graphData.nodes.slice().sort(function(a, b) {
            return a.title.localeCompare(b.title, 'ja');
        });

        sortedNodes.forEach(function(node) {
            var option = document.createElement('option');
            option.value = node.id;
            option.textContent = node.title + ' (ID: ' + node.id + ')';
            select.appendChild(option);
        });

        select.addEventListener('change', function() {
            var postId = this.value ? parseInt(this.value) : null;
            setCenterPost(postId);
        });

        // URLパラメータで初期表示する記事が指定されている場合
        if (kashiwazakiLinkMap.initialPostId) {
            select.value = kashiwazakiLinkMap.initialPostId;
        }

        // リセットボタン
        var resetBtn = document.getElementById('reset-view-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                select.value = '';
                setCenterPost(null);
                resetZoom();
            });
        }
    }

    function initGraph() {
        console.log('initGraph started');

        if (typeof d3 === 'undefined') {
            showError('D3.jsが読み込まれていません');
            return;
        }

        var container = document.getElementById('link-map-container');
        width = container.clientWidth;
        height = container.clientHeight;
        console.log('Container size:', width, 'x', height);

        svg = d3.select('#link-map-svg')
            .attr('width', width)
            .attr('height', height);

        // ズーム機能
        var zoom = d3.zoom()
            .scaleExtent([0.1, 4])
            .on('zoom', function(event) {
                g.attr('transform', event.transform);
            });

        svg.call(zoom);

        g = svg.append('g');

        // 矢印マーカー定義
        svg.append('defs').append('marker')
            .attr('id', 'arrowhead')
            .attr('viewBox', '-0 -5 10 10')
            .attr('refX', 20)
            .attr('refY', 0)
            .attr('orient', 'auto')
            .attr('markerWidth', 6)
            .attr('markerHeight', 6)
            .append('path')
            .attr('d', 'M 0,-5 L 10,0 L 0,5')
            .attr('fill', '#999');

        // リンクとノードのデータを準備
        var nodes = graphData.nodes.map(function(d) {
            return Object.assign({}, d);
        });

        var nodeById = {};
        nodes.forEach(function(node) {
            nodeById[node.id] = node;
        });

        var links = graphData.links.filter(function(link) {
            return nodeById[link.source] && nodeById[link.target];
        }).map(function(d) {
            return {
                source: nodeById[d.source],
                target: nodeById[d.target]
            };
        });

        // シミュレーション
        simulation = d3.forceSimulation(nodes)
            .force('link', d3.forceLink(links).id(function(d) { return d.id; }).distance(100))
            .force('charge', d3.forceManyBody().strength(-200))
            .force('center', d3.forceCenter(width / 2, height / 2))
            .force('collision', d3.forceCollide().radius(30));

        // リンク描画
        var link = g.append('g')
            .attr('class', 'links')
            .selectAll('line')
            .data(links)
            .enter().append('line')
            .attr('class', 'link')
            .attr('marker-end', 'url(#arrowhead)');

        // ノード描画
        var node = g.append('g')
            .attr('class', 'nodes')
            .selectAll('g')
            .data(nodes)
            .enter().append('g')
            .attr('class', 'node default')
            .call(d3.drag()
                .on('start', dragstarted)
                .on('drag', dragged)
                .on('end', dragended));

        node.append('circle')
            .attr('r', 8);

        node.append('text')
            .attr('dx', 12)
            .attr('dy', 4)
            .text(function(d) {
                var title = d.title;
                return title.length > 15 ? title.substring(0, 15) + '...' : title;
            });

        // ツールチップ
        var tooltip = d3.select('body').append('div')
            .attr('class', 'link-map-tooltip')
            .style('display', 'none');

        node.on('mouseover', function(event, d) {
            tooltip
                .style('display', 'block')
                .html('<strong>' + d.title + '</strong><br>ID: ' + d.id + '<br>タイプ: ' + d.type)
                .style('left', (event.pageX + 10) + 'px')
                .style('top', (event.pageY - 10) + 'px');
        })
        .on('mouseout', function() {
            tooltip.style('display', 'none');
        })
        .on('click', function(event, d) {
            document.getElementById('center-post-select').value = d.id;
            setCenterPost(d.id);
        });

        // シミュレーション更新
        simulation.on('tick', function() {
            link
                .attr('x1', function(d) { return d.source.x; })
                .attr('y1', function(d) { return d.source.y; })
                .attr('x2', function(d) { return d.target.x; })
                .attr('y2', function(d) { return d.target.y; });

            node.attr('transform', function(d) {
                return 'translate(' + d.x + ',' + d.y + ')';
            });
        });

        // グローバル参照を保持
        window.linkMapElements = {
            node: node,
            link: link,
            nodes: nodes,
            links: links,
            zoom: zoom
        };

        // 初期選択があれば中心にする
        if (kashiwazakiLinkMap.initialPostId) {
            setTimeout(function() {
                setCenterPost(kashiwazakiLinkMap.initialPostId);
            }, 500);
        }
    }

    function setCenterPost(postId) {
        // 文字列の場合は数値に変換
        postId = postId ? parseInt(postId, 10) : null;
        centerPostId = postId;
        var elements = window.linkMapElements;
        if (!elements) return;

        var linkedFromIds = {};  // この記事にリンクしている
        var linkedToIds = {};    // この記事からリンクしている

        if (postId) {
            elements.links.forEach(function(link) {
                if (link.target.id === postId) {
                    linkedFromIds[link.source.id] = true;
                }
                if (link.source.id === postId) {
                    linkedToIds[link.target.id] = true;
                }
            });
        }

        // ノードのクラス更新
        elements.node.attr('class', function(d) {
            if (d.id === postId) {
                return 'node center';
            } else if (linkedFromIds[d.id]) {
                return 'node linked-from';
            } else if (linkedToIds[d.id]) {
                return 'node linked-to';
            } else {
                return 'node default';
            }
        });

        // ノードサイズ更新
        elements.node.select('circle').attr('r', function(d) {
            if (d.id === postId) return 14;
            if (linkedFromIds[d.id] || linkedToIds[d.id]) return 10;
            return 6;
        });

        // リンクのハイライト
        elements.link
            .attr('class', function(d) {
                if (!postId) return 'link';
                if (d.target.id === postId) return 'link highlight incoming';
                if (d.source.id === postId) return 'link highlight outgoing';
                return 'link';
            })
            .attr('stroke-opacity', function(d) {
                if (!postId) return 0.4;
                if (d.target.id === postId || d.source.id === postId) return 1;
                return 0.1;
            });

        // 情報パネル更新
        updateInfoPanel(postId, linkedFromIds, linkedToIds);

        // 選択したノードを中心に表示
        if (postId) {
            var selectedNode = elements.nodes.find(function(n) { return n.id === postId; });
            if (selectedNode && selectedNode.x !== undefined && selectedNode.y !== undefined) {
                centerOnNode(selectedNode);
            }
        }
    }

    function centerOnNode(node) {
        if (!svg || !window.linkMapElements) return;

        var scale = 1.5;
        var x = -node.x * scale + width / 2;
        var y = -node.y * scale + height / 2;

        svg.transition()
            .duration(500)
            .call(
                window.linkMapElements.zoom.transform,
                d3.zoomIdentity.translate(x, y).scale(scale)
            );
    }

    function updateInfoPanel(postId, linkedFromIds, linkedToIds) {
        var infoPanel = document.getElementById('link-map-info');
        if (!postId) {
            infoPanel.style.display = 'none';
            return;
        }

        var node = graphData.nodes.find(function(n) { return n.id === postId; });
        if (!node) return;

        infoPanel.style.display = 'block';
        document.getElementById('info-title').textContent = node.title;
        document.getElementById('info-edit-link').href = node.url;

        var linksHtml = '';

        // リンク元
        var fromNodes = graphData.nodes.filter(function(n) { return linkedFromIds[n.id]; });
        if (fromNodes.length > 0) {
            linksHtml += '<div class="info-section"><h4>リンク元 (' + fromNodes.length + '件)</h4><ul>';
            fromNodes.forEach(function(n) {
                linksHtml += '<li><a href="' + n.url + '" target="_blank">' + n.title + '</a></li>';
            });
            linksHtml += '</ul></div>';
        }

        // リンク先
        var toNodes = graphData.nodes.filter(function(n) { return linkedToIds[n.id]; });
        if (toNodes.length > 0) {
            linksHtml += '<div class="info-section"><h4>リンク先 (' + toNodes.length + '件)</h4><ul>';
            toNodes.forEach(function(n) {
                linksHtml += '<li><a href="' + n.url + '" target="_blank">' + n.title + '</a></li>';
            });
            linksHtml += '</ul></div>';
        }

        if (!linksHtml) {
            linksHtml = '<p>リンクがありません</p>';
        }

        document.getElementById('info-links').innerHTML = linksHtml;
    }

    function resetZoom() {
        if (svg && window.linkMapElements) {
            svg.transition().duration(500).call(
                window.linkMapElements.zoom.transform,
                d3.zoomIdentity.translate(width / 2, height / 2).scale(1).translate(-width / 2, -height / 2)
            );
        }
    }

    function dragstarted(event, d) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        d.fx = d.x;
        d.fy = d.y;
    }

    function dragged(event, d) {
        d.fx = event.x;
        d.fy = event.y;
    }

    function dragended(event, d) {
        if (!event.active) simulation.alphaTarget(0);
        d.fx = null;
        d.fy = null;
    }

    // DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
